@extends('layouts.core')

@section('title', 'Invoice Management')
    
@section('content')
    @include('invoices.header')
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Invoice Management</h5>
            <div class="card-content p-2">
                <div class="table-responsive">
                    <table class="table table-borderless datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Invoice#</th>
                                <th>Order Number#</th>                                
                                <th>Customer Name</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $i => $row)
                                <tr>
                                    <th scope="row">{{ $i+1 }}</th>
                                    <td>{{ dateFormat($row->date) }}</td>
                                    <td>{{ $row->zoho_invoice_number }}</td>
                                    <td>{{ $row->order_number  }}</td>
                                    <td>{{ $row->customer_name  }}</td>
                                    <td>{{ numberFormat($row->total)  }}</td>
                                    <td>{!! $row->action_buttons !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('script')
<script>
    
</script>    
@stop
