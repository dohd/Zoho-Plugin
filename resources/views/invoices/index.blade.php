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
                                <th>DATE</th>
                                <th>INVOICE#</th>
                                <th>CUSTOMER NAME</th>
                                <th>STATUS</th>
                                <th>DUE DATE</th>
                                <th>AMOUNT</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $i => $row)
                                <tr>
                                    <th scope="row">{{ $i+1 }}</th>
                                    <td>{{ dateFormat($row->date, 'd M Y') }}</td>
                                    <td>{{ $row->zoho_invoice_number }}</td>
                                    <td>{{ $row->customer_name  }}</td>
                                    <td>
                                        @if ($row->zoho_status == 'draft')
                                            <span class="badge bg-warning status-btn" style="cursor: pointer;" data-id="{{$row->id}}" data-bs-toggle="modal" data-bs-target="#statusModal">
                                                draft<i class="bi bi-caret-down-fill"></i>
                                            </span>
                                        @else
                                            <span class="badge bg-success">{{ $row->zoho_status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ dateFormat($row->due_date, 'd M Y') }}</td>
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
    @include('invoices.partial.status_modal')
@stop

@section('script')
<script>
    $('.status-btn').click(function() {
        $('#invoiceId').val($(this).attr('data-id'));
    });
</script>    
@stop
