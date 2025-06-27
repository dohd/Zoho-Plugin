<?php

namespace App\Http\Controllers\invoices;

use App\Http\Controllers\Controller;
use App\Http\Services\InvoiceService;
use App\Models\invoice\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoicesController extends Controller
{
    protected $service;

    public function __construct()
    {
        $this->service = new InvoiceService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $invoices = Invoice::latest()->get();
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
        // dd($input);
        
        // $response = $this->service->postInvoice();
        // $response = $this->service->markSentInvoice(6519309000000130001);
        // $response = $this->service->postInventoryAdjustment();
        // $response = $this->service->getItem(6519309000000099166);
        // $response = $this->service->getCompositeItem(6519309000000099199);
        // $response = $this->service->getCompositeItems(['name_contains' => 'Business Suite Setup']);
        // return $response;

        // post invoice
        $invResponse = $this->service->postInvoice();
        $zohoInvoice = @$invResponse->invoice; 
        $invItems = @$invResponse->invoice->line_items;
        if ($invItems && count($invItems)) {
            foreach ($invItems as $invItem) {
                $itemResp = $this->service->getItem($invItem->item_id);
                $stockItem = @$itemResp->item;
                printLog('**stock item**', json_encode($invItems));
                if ($stockItem && $stockItem->product_type == 'service') {
                    $itemName = $stockItem->name;
                    // fetch composite items with replica name
                    $comItemResponse = $this->service->getCompositeItems(['name_contains' => $itemName]);
                    $comItems = @$comItemResponse->composite_items;
                    printLog('**composite items**', json_encode($comItems));
                    if ($comItems && count($comItems)) {
                        $comItem = $comItems[0];
                        // fetch specific composite item 
                        $comItemResponse1 = $this->service->getCompositeItem($comItem->composite_item_id);
                        $mappedItems = @$comItemResponse1->composite_item->mapped_items;
                        printLog('**mapped items**', json_encode($mappedItems));
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
                            printLog('**adjustment Lines**', json_encode($adjustmentLines));
                            $adjResponse = $this->service->postInventoryAdjustment([
                              "reason" => "Inventory Revaluation",
                              "description" => "Sales Invoice {$zohoInvoice->invoice_number}",
                              "date" => date('Y-m-d'),
                              "warehouse_id" => "6519309000000093087", // dynamic
                              "line_items" => $adjustmentLines
                            ]);                            
                        }
                    }
                }
            }
            $this->service->markSentInvoice($zohoInvoice->invoice_id);
        }

        return ['invoice' => $invResponse, 'adjustment' => @$adjResponse];



        try {   
            foreach ($input as $key => $value) {
                if ($key == 'gross_salary') $input[$key] = numberClean($value);
                if (strpos($key, 'date') !== false) {
                    $input[$key] = Carbon::parse($value)->format('Ymd');
                }
            }
            $invoice = Invoice::create($input);
            return redirect(route('invoices.index'))->with(['success' => 'Invoice created successfully']);
        } catch (\Throwable $th) {
            return errorHandler('Error creating Invoice!', $th);
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

        foreach ($invoice->getAttributes() as $key => $value) {
            if (strpos($key, 'date') !== false && $value) {
                $invoice[$key] = dateFormat($value, 'Y-m-d');
            }
        }

        return view('invoices.view', compact('invoice', 'employeeCols'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Invoice $invoice)
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

        foreach ($invoice->getAttributes() as $key => $value) {
            if (strpos($key, 'date') !== false && $value) {
                $invoice[$key] = dateFormat($value, 'Y-m-d');
            }
        }

        return view('invoices.edit', compact('invoice', 'employeeCols'));
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
        // dd($request->all());
        $input = $request->except('_token');
        try {      
            foreach ($input as $key => $value) {
                if ($key == 'gross_salary') $input[$key] = numberClean($value);
                if (strpos($key, 'date') !== false) {
                    $input[$key] = Carbon::parse($value)->format('Ymd');
                }
            }

            $invoice->update($input);
            return redirect(route('invoices.index'))->with(['success' => 'Invoice updated successfully']);
        } catch (\Throwable $th) {
            return errorHandler('Error updating Invoice!', $th);
        }
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
            $invoice->delete();
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
        // dd($items);
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
}
