<style type="text/css">
	.select-w {
		width: 100%;
	}
	tfoot td {
	  border: none !important;
	}
	.cursor-pointer {
		cursor: pointer;
	}
</style>

<div class="row g-3 align-items-center bg-light pb-3 mb-2">
  <div class="col-md-2">
    <label for="customer" class="col-form-label text-danger">Customer Name*</label>
  </div>
  <div class="col-md-5">
    <select name="customer_id" id="customer" class="form-select select-w" data-placeholder="Search Customer">
    	<option></option>
	</select>
  </div>  
</div>
<div class="row g-3 align-items-center mb-2">
  <div class="col-md-2">
    <label for="invoiceNo" class="col-form-label text-danger">Invoice#*</label>
  </div>
  <div class="col-md-4">
    {{ Form::text('invoice_no', null, ['class' => 'form-control', 'id' => 'invoiceNo']) }}
  </div>  
</div>
<div class="row g-3 align-items-center mb-2">
  <div class="col-md-2">
    <label for="orderNo" class="col-form-label">Order Number</label>
  </div> 
    <div class="col-md-4">
	  {{ Form::text('order_no', null, ['class' => 'form-control', 'id' => 'orderNo']) }}
	</div> 
</div>
<div class="row g-3 align-items-center mb-4">
  <div class="col-md-2">
    <label for="invoiceDate" class="col-form-label text-danger">Invoice Date</label>
  </div>
  <div class="col-md-4">
    {{ Form::date('invoice_date', null, ['class' => 'form-control', 'id' => 'invoiceDate']) }}
  </div>  
  <div class="col-md-6">
  	<div class="row g-3 align-items-center ps-2">
	  <div class="col-md-2">
	    <label for="terms" class="col-form-label">Terms</label>
	  </div>
	  <div class="col-md-4">
	    <select name="terms_id" id="terms" class="form-select">
    	    <option value="net15">Net 15</option>
    	    <option value="net30">Net 30</option>
    	    <option value="net45">Net 45</option>
    	    <option value="net60">Net 60</option>
    	    <option value="onreceipt" selected>Due on Receipt</option>
    	    <option value="endmonth">Due end of the month</option>
    	    <option value="endnextmonth">Due end of the next month</option>
	    </select>
	  </div> 
	  <div class="col-md-2">
	    <label for="dueDate" class="col-form-label">Due Date</label>
	  </div>
	  <div class="col-md-4">
        {{ Form::date('due_date', null, ['class' => 'form-control', 'id' => 'dueDate']) }}
      </div>  
  	</div>
  </div>
</div>
<hr>
<div class="row g-3 align-items-center mb-4 mt-2">
  <div class="col-md-2">
    <label for="salesPerson" class="col-form-label">Sales Person</label>
  </div>
  <div class="col-md-4">
    <select name="sales_person_id" id="salesPerson" class="form-control select-w" data-placeholder="Search Sales Person">
    </select>
  </div> 
</div>
<hr>
<div class="row g-3 align-items-center mb-4 mt-2">
  <div class="col-md-2">
    <label for="description" class="col-form-label">Description</label>
  </div>
  <div class="col-md-4">
    {{ Form::textarea('description', null, ['class' => 'form-control', 'id' => 'description', 'rows' => '1', 'placeholder' => 'What is this invoice for']) }}
  </div> 
</div>
<hr>

<!-- Item Table -->
<div class="row mt-3">
    <div class="col-md-12">
    	<h5><b>Item Table</b></h5>
        <table class="table table-bordered" id="itemTbl">
            <thead>
                <tr>
                  <th width="40%">ITEM DETAILS</th>
                  <th class="text-end" >QTY</th>
                  <th class="text-end" >RATE</th>
                  <th class="text-end" >Amount</th>
                  <th></th>
                </tr>
            </thead>
            <tbody>
            	<tr class="header-row" style="display: none;">
                    <td colspan="4">
                    	<input type="text" name="name[]" class="form-control">                   	
                    </td>
                    <td><span class="cursor-pointer del"><i class="bi bi-x-circle text-danger"></i></span></td>
                </tr>
                <tr class="item-row">
                    <td>
                    	<textarea name="name[]" class="form-control dropdown-toggle name" data-bs-toggle="dropdown" aria-expanded="false" rows="2"></textarea> 
						<ul class="dropdown-menu">
						    <li><a class="dropdown-item" href="#">Action</a></li>
						    <li><a class="dropdown-item" href="#">Another action</a></li>
						    <li><a class="dropdown-item" href="#">Something else here</a></li>
						    <li><hr class="dropdown-divider"></li>
						    <li><a class="dropdown-item" href="#">Separated link</a></li>
						</ul>                    	
                    </td>
                    <td><input type="text" name="qty[]" class="form-control qty text-end" value="1.00"></td>
                    <td><input type="text" name="rate[]" class="form-control rate text-end" value="0.00"></td>
                    <td class="text-end"><h5 class="amount fw-bold" style="margin-top: 0.5rem;">0.00</h5></td>
                    <td><span class="cursor-pointer del"><i class="bi bi-x-circle text-danger"></i></span></td>
                </tr>
            </tbody>

            <tfoot>
            	<tr>
            		<td>
            			<div class="form-group">
	            			<div class="d-inline">
		            			<span id="addRow" class="badge bg-primary mr-1 cursor-pointer"><i class="bi bi-plus-circle-fill"></i> Add New Row</span>
		            			<span id="addHeader" class="badge bg-success cursor-pointer"><i class="bi bi-plus-circle-fill"></i> Add New Header</span>            				
	            			</div>
            			</div>            			
            			<div class="form-group mt-2">
	            			<div><label for="customerNotes" class="col-form-label">Customer Notes</label></div>
						    {{ Form::textarea('customer_notes', null, ['class' => 'form-control', 'id' => 'customerNotes', 'rows' => '2', 'placeholder' => 'Thanks for your business']) }}
            			</div>
            		</td>
            		<td colspan="3">
            			<div class="p-2 mt-1 bg-light">
	            			<div class="row g-3 align-items-center mt-1">
							  <div class="col-md-6">
							    <h5 class="text-end fw-bold">Subtotal</h5>
							  </div>
							  <div class="col-md-6">
							    <h5 class="subtotal text-end">0.00</h5>
							  </div> 
							</div>
							<hr class="m-1">
							<div class="row g-3 align-items-center mt-1">
							  <div class="col-md-6">
							    <h5 class="text-end fw-bold">Total</h5>
							  </div>
							  <div class="col-md-6">
							    <h5 class="total text-end">0.00</h5>
							  </div> 
							</div>
            			</div>
            		</td>
            		<td></td>
            	</tr>
            </tfoot>
        </table>
    </div>
</div>
<br>

@section('script')
<script>
	const config = {
		customerSelect2: {
			allowClear: true,
		},
		salesPersonSelect2: {
			allowClear: true,
		},
	};

	const Form = {
		headerRowHtml: $('#itemTbl .header-row:first').clone(),
		itemRowHtml: $('#itemTbl .item-row:first').clone(),

		init() {
			$('#customer').select2(config.customerSelect2);
		    $('#salesPerson').select2(config.salesPersonSelect2);

		    $('#addRow').click(Form.onClickAddRow);
		    $('#addHeader').click(Form.onClickAddHeader);
		    $(document).on('click', '.del', Form.onClickDelete);
		    $(document).on('keyup', '.qty, .rate', Form.onKeyQtyRate);
		},

		onClickAddRow() {
			const row = Form.itemRowHtml.clone();
			$('#itemTbl tbody').append(row);
		},
		onClickAddHeader() {
			const row = Form.headerRowHtml.clone();
			row.removeAttr('style');
			$('#itemTbl tbody').append(row);
		},
		onClickDelete() {
			$(this).parents('tr:first').remove();
		},

		onKeyQtyRate() {
			const tr = $(this).parents('tr:first');
			const qty = accounting.unformat(tr.find('.qty').val());
			const rate = accounting.unformat(tr.find('.rate').val());
			const amount = qty * rate;
			tr.find('.amount').html(accounting.formatNumber(amount));
			Form.computeTotals();
		},

		computeTotals() {
			let subtotal = 0;
			let total = 0;
			$('#itemTbl .amount').each(function() {
				const amount = accounting.unformat($(this).html());
				subtotal += amount;
				total += amount;
			});
			$('.subtotal').html(accounting.formatNumber(subtotal));
			$('.total').html(accounting.formatNumber(total));
		},
	}

	$(Form.init);
</script>    
@stop