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
                url: "{{ route('invoices.search_salespersons') }}",
                dataType: 'json',
                delay: 250,
                type: 'GET',
                data: ({term}) => ({
                	salesperson_name_contains: term,
                }),            
                processResults: response => {
                    return { 
                    	results: (response.data || []).map(v => ({
                    		id: v.salesperson_id,
                    		text: v.salesperson_name + (v.salesperson_email? ` (${v.salesperson_email})` : ''), 
                    	})) 
                    }
                },
            }
		},
	};

	const Form = {
		headerRowHtml: $('#itemTbl .header-row:first').clone(),
		itemRowHtml: $('#itemTbl .item-row:first').clone(),
		itemSpinner: '<li>{!! spinner() !!}</li>',

		init() {
			$('#customer').select2(config.customerSelect2);
			$('#salesPerson').select2(config.salesPersonSelect2);

			$('#addRow').click(Form.onClickAddRow);
			$('#addHeader').click(Form.onClickAddHeader);
			$('#itemTbl').on('click', '.del', Form.onClickDelete);
			$('#itemTbl').on('keyup', '.qty, .rate', Form.onKeyQtyRate);
			$('#itemTbl').on('click', '.dropdown-item', Form.onClickItem);
			$('#itemTbl').on('keyup', '.name', Form.onKeyName);
			$('#itemTbl').on('click', '.name', function() { $(this).keyup() });
			$('form').submit(Form.onSubmit);

			setTimeout(() => Form.loadPaymentTerms(), 500);		
		},

		onSubmit(e) {
			e.preventDefault();
			// const formData = $(this).serialize();
			const formData = new FormData(this);
			$.ajax({
			    url: "{{ route('invoices.store') }}",
			    type: 'POST',
			    data: formData,
			    contentType: false,
			    processData: false,
			    success: function(response) {
			      // console.log('Uploaded successfully', response);
			      flashMessage(response)
			    },
			    error: function(xhr,status,err) {
			      // console.error('Upload failed:', xhr.responseText, err);
			      flashMessage(xhr)
			    }
			  });
		},

		loadPaymentTerms() {
			$.get("{{ route('invoices.paymentterms') }}")
			.then(resp => {
				if (resp.data && resp.data.payment_terms.length) {
					const terms = resp.data.payment_terms;
					terms.forEach(term => {
						const optionEl = `<option value="${term.payment_terms}">${term.payment_terms_label}</option>`;
						$('#terms').append(optionEl);
					});
				}
			})
			.fail((xhr,status,err) => {
				console.log(err)
			});			
		},

		onKeyName() {
			const dropdown = $(this).next();
			dropdown.html(Form.itemSpinner);
			setTimeout(() => {
				$.get("{{ route('invoices.search_items') }}", {
					name_contains: $(this).val(),
					filter_by: 'Status.Active',
					per_page: 6,
				})
				.then(resp => {
					dropdown.html(resp);
				})
				.fail((xhr,status,err) => {
					dropdown.html(`<li class="text-danger ps-2">Error Loading Data ...<li>`);
					console.log(err);
				});
			}, 250);
		},

		onClickItem() {
			const itemId = $(this).attr('item_id');
			const name = $(this).attr('name');
			const rate = accounting.unformat($(this).attr('rate'));
			const tr = $(this).parents('tr:first');
			tr.find('.item-id').val(itemId);
			tr.find('.name').val(name);
			tr.find('.rate').val(accounting.formatNumber(rate)).keyup();
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
			tr.find('.amount-inp').val(accounting.formatNumber(amount));
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