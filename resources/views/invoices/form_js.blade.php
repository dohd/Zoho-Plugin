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
                    		name: v.contact_name, 
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
                    		email: v.salesperson_email,
                    		name: v.salesperson_name,
                    	})) 
                    }
                },
            }
		},
		locationSelect2: {
			allowClear: true,
			ajax: {
                url: "{{ route('invoices.itemlocations') }}",
                dataType: 'json',
                delay: 250,
                type: 'GET',
                data: ({term}) => ({
                	location_name_contains: term,
                }),            
                processResults: response => {
                    return { 
                    	results: (response.locations || []).map(v => ({
                    		id: v.location_id,
                    		text: v.location_identification_number? 
                    			`${v.location_identification_number} - ${v.location_name}`:
                    			v.location_name,
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
		btnSpinner: '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>',

		init() {
			$('#customer').select2(config.customerSelect2);
			$('#salesPerson').select2(config.salesPersonSelect2);
			$('#location').select2(config.locationSelect2);

			$('#customer').change(Form.onChangeCustomer);
			$('#terms').change(Form.onChangeTerms);
			$('#salesPerson').change(Form.onChangeSalesPerson);
			$('#addRow').click(Form.onClickAddRow);
			$('#addHeader').click(Form.onClickAddHeader);
			$('#itemTbl').on('click', '.del', Form.onClickDelete);
			$('#itemTbl').on('keyup', '.qty, .rate', Form.onKeyQtyRate);
			$('#itemTbl').on('click', '.dropdown-item', Form.onClickItem);
			$('#itemTbl').on('keyup', '.name', Form.onKeyName);
			$('#itemTbl').on('click', '.name', function() { $(this).keyup() });
			$('form').submit(Form.onSubmit);

			Form.loadPaymentTerms();
			Form.loadCurrencies();
		},

		onSubmit(e) {
			e.preventDefault();
			$('#submitBtn').attr('disabled', true).html(`Submit ${Form.btnSpinner}`);
			const formData = new FormData(this);

			$.ajax({
			    url: "{{ route('invoices.store') }}",
			    type: 'POST',
			    data: formData,
			    contentType: false,
			    processData: false,
			    success: function(response) {
			      $('#submitBtn').attr('disabled', false).html('Submit');
			      flashMessage(response);			      
			    },
			    error: function(xhr,status,err) {
			      // console.error('Upload failed:', xhr.responseText, err);
			      $('#submitBtn').attr('disabled', false).html('Submit');
			      flashMessage(xhr);
			    }
			});
		},

		loadCurrencies() {
			$.get("{{ route('invoices.currencies') }}")
			.then(resp => {
				if (resp.currencies && resp.currencies.length) {
					const currencies = resp.currencies;
					currencies.forEach(v => {
						if (v.is_base_currency) {
							$('#currencyId').val(v.currency_id);
							$('#currencyCode').val(v.currency_code);
							$('#currencyRate').val(v.exchange_rate);
						}
					});
				}
			})
			.fail((xhr,status,err) => {
				console.log(err)
			});			
		},

		loadPaymentTerms() {
			$.get("{{ route('invoices.paymentterms') }}")
			.then(resp => {
				if (resp.data && resp.data.payment_terms.length) {
					const terms = resp.data.payment_terms;
					terms.forEach(term => {
						const optionEl = `
							<option 
								value="${term.payment_terms_id}"
								terms="${term.payment_terms}"
								label="${term.payment_terms_label}"
							>
								${term.payment_terms_label}
							</option>`;
						$('#terms').append(optionEl);
					});
					$('#terms').change();
				}
			})
			.fail((xhr,status,err) => {
				console.log(err)
			});			
		},

		onChangeCustomer() {
			let data = $(this).select2('data');
			if (data.length) {
				$('#customerName').val(data[0]['name']);
			}
		},

		onChangeSalesPerson() {
			let data = $(this).select2('data');
			if (data.length) {
				$('#salesPersonName').val(data[0]['name']);
				$('#salesPersonEmail').val(data[0]['email']);
			}
		},

		onChangeTerms() {
			const opt = $(this).find(':selected');
			$('#paymentTermsLabel').val(opt.attr('label'));
			$('#paymentTerms').val(opt.attr('terms'));
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
			const rate = accounting.unformat($(this).attr('rate'));
			const tr = $(this).parents('tr:first');
			tr.find('.rate').val(accounting.formatNumber(rate)).keyup();
			tr.find('.item-id').val($(this).attr('item_id'));
			tr.find('.name').val($(this).attr('name'));
			tr.find('.descr').val($(this).attr('descr'));
			tr.find('.sku').val($(this).attr('sku'));
			tr.find('.item-type').val($(this).attr('item_type'));	
			tr.find('.unit').val($(this).attr('unit'));	
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
			let taxable = 0;
			let tax = 0;
			let subtotal = 0;
			let total = 0;
			$('#itemTbl .amount').each(function() {
				const amount = accounting.unformat($(this).html());
				subtotal += amount;
				total += amount;
			});
			$('.subtotal').html(accounting.formatNumber(subtotal));
			$('.total').html(accounting.formatNumber(total));
			$('#subtotal').val(accounting.formatNumber(subtotal));
			$('#total').val(accounting.formatNumber(total));
		},
	}

	$(Form.init);
</script>