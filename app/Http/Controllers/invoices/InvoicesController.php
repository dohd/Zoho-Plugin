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
        // dd($request->all());
        $input = $request->except('_token');
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
}
