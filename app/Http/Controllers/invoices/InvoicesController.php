<?php

namespace App\Http\Controllers\invoices;

use App\Http\Controllers\Controller;
use App\Http\Services\ZohoService;
use App\Models\invoice\Invoice;
use App\Models\invoice\InvoiceItem;
use App\Models\stockadj\Stockadj;
use App\Models\stockadj\StockadjItem;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoicesController extends Controller
{
    protected $service;

    public function __construct()
    {
        $this->service = new ZohoService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $invoices = Invoice::whereNull('deleted_at')->latest()->get();

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {   
        $excluded = [
            'id', 'ins', 'user_id', 'created_at', 'updated_at',
        ];
        $employeeCols = DB::table('information_schema.columns')
        ->select('COLUMN_NAME')
        ->where('table_schema', DB::getDatabaseName()) // current DB
        ->where('table_name', 'invoices')
        ->whereNotIn('COLUMN_NAME', $excluded)
        ->orderBy('ORDINAL_POSITION')
        ->pluck('COLUMN_NAME')
        ->toArray();
        $employeeCols = array_chunk($employeeCols, 4);

        return view('invoices.create', compact('employeeCols'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->except('_token');
        $dataItems = $request->only([
            'item_id', 'row_index', 'name', 'description', 'unit', 
            'quantity', 'rate', 'amount', 'tax_id', 'tax_percentage',
            'item_tax', 'product_type', 'sku'
        ]);
        $data = $request->except(['_token', ...array_keys($dataItems)]);
        $zohoInvoice = null;
        $zohoAdjs = [];
        $adjResponses = [];

        try {
            // sanitize
            foreach ($data as $key => $value) {
                if (in_array($key, ['date', 'due_date'])) $data[$key] = databaseDate($value);
                if (in_array($key, ['taxable', 'tax', 'subtotal', 'total'])) {
                    $data[$key] = numberClean($value);
                }
            }
            foreach ($dataItems as $key => $value) {
                if (in_array($key, ['row_index', 'amount', 'item_tax', 'quantity', 'rate', 'tax_percentage'])) {
                    $dataItems[$key] = array_map(fn($v) => numberClean($v), $value);
                }
            }

            DB::beginTransaction();

            // create local invoice
            $invoice = Invoice::create($data);
            // create local invoice items
            $dataItems['invoice_id'] = array_fill(0, count($dataItems['name']), @$invoice->id);
            $dataItems['user_id'] = array_fill(0, count($dataItems['name']), @$invoice->user_id);
            $dataItems = databaseArray($dataItems);
            $dataItems = array_filter($dataItems, fn($v) => $v['name']);
            InvoiceItem::insert($dataItems);

            // Post zoho invoice
            $adjResponses = [];
            $invResponse = $this->service->postInvoice($invoice);
            $zohoInvoice = @$invResponse->invoice; 
            $invItems = @$invResponse->invoice->line_items;
            if ($invItems && count($invItems)) {
                Log::info('Invoice Items: ' . strval(count($invItems)));
                foreach ($invItems as $invItem) {
                    $itemResp = $this->service->getItem($invItem->item_id);
                    $stockItem = @$itemResp->item;

                    if ($stockItem && $stockItem->product_type == 'service') {
                        $itemName = $stockItem->name;
                        // fetch composite items with replica name
                        $comItemResponse = $this->service->getCompositeItems(['name_contains' => $itemName]);
                        $comItems = @$comItemResponse->composite_items;
                        Log::info('Composite Items: ' . strval(count($comItems)));

                        if ($comItems && count($comItems)) {
                            $comItem = $comItems[0];
                            // fetch specific composite item 
                            $comItemResponse1 = $this->service->getCompositeItem($comItem->composite_item_id);
                            $mappedItems = @$comItemResponse1->composite_item->mapped_items;
                            Log::info('Mapped Composite Items: ' . strval(count($mappedItems)));

                            // adjust inventory for mapped items
                            if ($mappedItems && count($mappedItems)) {
                                $adjustmentLines = [];
                                foreach ($mappedItems as $mappedItem) {
                                    if ($mappedItem->product_type == 'goods') {
                                        $adjustmentLines[] = [
                                            "item_id" => $mappedItem->item_id,
                                            "quantity_adjusted" => -$mappedItem->quantity*$invItem->quantity
                                        ];
                                    }
                                }
                                Log::info('Adjustment Lines: ' . strval(count($adjustmentLines)));
                                $adjResponses[] = $this->service->postInventoryAdjustment([
                                  "reason" => "Inventory Revaluation",
                                  "description" => "Sales Invoice {$zohoInvoice->invoice_number}",
                                  "adjustment_type" => "quantity",
                                  "date" => $zohoInvoice->date,
                                  "location_id" => $zohoInvoice->location_id, // dynamic
                                  "line_items" => $adjustmentLines
                                ]);                            
                            }
                        }
                    }
                }
                $this->service->markSentInvoice($zohoInvoice->invoice_id);
            }
            $invoice->update([
                'zoho_invoice_id' => $zohoInvoice->invoice_id,
                'zoho_invoice_number' => $zohoInvoice->invoice_number,
                'zoho_status' => $zohoInvoice->status,
                'zoho_exchange_rate' => $zohoInvoice->exchange_rate,
                'zoho_invoice_url' => $zohoInvoice->invoice_url,
            ]);

            // 

            $zohoAdjs = [];
            foreach ($adjResponses as $adjResponse) {
                $zohoAdj = @$adjResponse->inventory_adjustment;
                if ($zohoAdj) {
                    $zohoAdjs[] = $zohoAdj;
                    // create local stock adjustment
                    $stockAdj = Stockadj::create([
                        'reason' => $zohoAdj->reason,
                        'description' => $zohoAdj->description,
                        'adjustment_type' => $zohoAdj->adjustment_type,
                        'date' => $zohoAdj->date,
                        'location_id' => $zohoAdj->location_id,
                        'invoice_id' => $invoice->id,
                        'inventory_adjustment_id' => $zohoAdj->inventory_adjustment_id,
                    ]);
                    foreach ($zohoAdj->line_items as $item) {
                        StockadjItem::create([
                            'stock_adj_id' => $stockAdj->id,
                            'item_id' => $item->item_id,
                            'quantity_adjusted' => $item->quantity_adjusted,
                            'zoho_line_item_id' => $item->line_item_id,
                            'zoho_item_name' => $item->name,
                            'zoho_adjustment_account_id' => $item->adjustment_account_id,
                            'zoho_adjustment_account_name' => $item->adjustment_account_name,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success', 
                'message' => 'Invoice created successfully',
                'redirectTo' => route('invoices.create'),
                // 'data' => compact('zohoInvoice', 'zohoAdjs'),
            ]);

        } catch (Exception $e) {
            // Clear Zoho entries
            foreach ($zohoAdjs as $zohoAdj) {
                $this->service->deleteInventoryAdjustment($zohoAdj->inventory_adjustment_id);
                Log::info('Zoho Adjustment Cleared: ' . $zohoAdj->inventory_adjustment_id);
            }
            if ($zohoInvoice) {
                $this->service->deleteInvoice($zohoInvoice->invoice_id);
                Log::info('Zoho Invoice Cleared: ' . $zohoAdj->inventory_adjustment_id);
            }
            
            $msg = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
            Log::error($msg);
            return response()->json([
                'status' => 'error', 
                'message' => 'Invoice creation failed: ' . $msg,
                // 'data' => compact('zohoInvoice', 'zohoAdjs'),
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {
        return view('invoices.view', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Invoice $invoice)
    {
        return view('invoices.edit', compact('invoice'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Invoice $invoice, Request $request)
    { 
        // 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoice $invoice)
    {
        try {   
            $invoice->update(['deleted_at' => now()]);
            if ($invoice->stockAdj) $invoice->stockAdj()->update(['deleted_at' => now()]); 

            return redirect(route('invoices.index'))->with(['success' => 'Invoice deleted successfully']);
        } catch (\Throwable $th) {
            return errorHandler('Error deleting Invoice!', $th);
        }
    }

    /**
     * Invoice Document Upload
     * */
    public function documentUpload(Request $request)
    {
        $request->validate([
            'file' => 'required',
            'employee_id' => 'required',
        ]);
        $file = $request->file('file');
        // dd($file);

        try {  
            $invoice = Invoice::findOrFail(request('employee_id'));

            // $fileName = $this->storeFile($file);
            // EmployeeDoc::create([
            //     'employee_id' => $request->employee_id,
            //     'origin_name' => $file->getClientOriginalName(),
            //     'name' => $fileName,
            //     'caption' => $request->caption,
            //     'doc_type' => $request->doc_type,
            // ]);

            return redirect(route('invoices.show', $invoice))->with(['success' => 'Document uploaded successfully']);
        } catch (\Throwable $th) {
            return errorHandler('Error uploading Document!', $th);
        }
    }

    /***
     * Document Download
     * **/
    public function documentDownload($doc) 
    {
        // $document = EmployeeDoc::findOrFail($doc);
        // $path = 'employee_docs/' . $document->name;
        // $filepath = Storage::disk('public')->path($path);
        // if (!file_exists($filepath)) abort(404, 'File not found.');
        // return Storage::disk('public')->download($path);
    }

    /**
     * Store file in local storage
     */
    public function storeFile($file)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        Storage::disk('public')->put("employee_docs/{$fileName}", file_get_contents($file->getRealPath()));
        return $fileName;
    }

    /** 
     * Search Contacts
     * */
    public function searchContacts(Request $request)
    {
        $params = $request->all();
        $contacts = $this->service->getContacts($params);
        
        return response()->json($contacts);
    }

    /** 
     * Search Sales Person
     * */
    public function searchSalesPersons(Request $request)
    {
        $params = $request->all();
        $salesPerson = $this->service->getSalesPerson($params);
        
        return response()->json($salesPerson);
    }

    /** 
     * Search Items
     * */
    public function searchItems(Request $request)
    {
        $params = $request->all();
        $itemsObj = $this->service->getItems($params);
        $items = @$itemsObj->items ?: [];

        // filter out composite items
        $sku = config('ZOHO_COMPOSITE_SKU');
        if ($sku) $items = array_filter($items, fn($v) => !(stripos($v->sku, $sku) !== false));
        
        return view('invoices.partial.dropdown_item', compact('items'));
    }

    /** 
     * Payment Terms
     * */
    public function paymentTerms(Request $request)
    {
        $params = $request->all();
        $terms = $this->service->paymentTerms($params);
        
        return response()->json($terms);
    }

    /** 
     * Item Locations
     * */
    public function itemLocations(Request $request)
    {
        $params = $request->all();
        $locations = $this->service->getLocations($params);
        
        return response()->json($locations);
    }

    /** 
     * Currencies
     * */
    public function currencies(Request $request)
    {
        $params = $request->all();
        $currencies = $this->service->getCurrencies($params);
        
        return response()->json($currencies);
    }
}
