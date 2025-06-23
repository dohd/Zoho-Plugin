<script>
	const config = {
		customerSelect2: {
			allowClear: true,
			ajax: {
                url: "{{ route('invoices.search_contacts') }}",
                dataType: 'json',
                delay: 250,
                type: 'GET',
                data: ({term}) => ({
                	contact_type: 'customer',
                	contact_name_contains: term,
                }),            
                processResults: response => {
                    return { 
                    	results: (response.contacts || []).map(v => ({
                    		id: v.contact_id,
                    		text: v.contact_name, 
                    	})) 
                    }
                },
            }
		},
		salesPersonSelect2: {
			allowClear: true,
			ajax: {
                url: "{{ route('invoices.search_contacts') }}",
                dataType: 'json',
                delay: 250,
                type: 'GET',
                data: ({term}) => ({
                	contact_type: 'customer',
                	contact_name_contains: term,
                }),            
                processResults: response => {
                    return { 
                    	results: (response.contacts || []).map(v => ({
                    		id: v.contact_id,
                    		text: v.contact_name, 
                    	})) 
                    }
                },
            }
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
			$('#itemTbl').on('click', '.del', Form.onClickDelete);
			$('#itemTbl').on('keyup', '.qty, .rate', Form.onKeyQtyRate);
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