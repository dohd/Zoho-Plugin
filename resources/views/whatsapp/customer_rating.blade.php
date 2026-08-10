@extends('layouts.core')
@section('title', 'Customer Ratings')
    
@section('content')
    @include('whatsapp.partial.customer_rating_header')

    <div class="card">
        <div class="card-body">
            <div class="card-content p-2">
                <div class="row mt-2">
                    <div class="col-md-2 col-2">
                        <select id="status" class="form-control">
                            <option value="">-- Filter Status --</option>
                            @foreach ($ratings as $status)
                                <option value="{{ $status }}" >{{ ucfirst(str_replace("_", " ", $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-2">
                        <select id="optOut" class="form-control">
                            <option value="">-- Filter Opt-out --</option>
                            @foreach (range(1,1) as $status)
                                <option value="{{ $status }}">YES</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="card-content p-2">
                <div class="table-responsive">
                    <!-- LIMIT CONTROL -->
                    <label class="mt-3">
                        <select id="dbLimit" onchange="updateLimit()">
                          <option value="10">10</option>
                          <option value="25">25</option>
                          <option value="50">50</option>
                          <option value="200">200</option>
                        </select> entries per page
                    </label>  

                    <style>
                        .custom-selectable-table tbody tr:hover td {
                          #background-color: #e3f2fd; /* Light blue selection color */
                          color: #0d6efd;            /* Bootstrap primary blue text color */
                          cursor: pointer;           /* Changes cursor to a hand pointer */
                          transition: background-color 0.15s ease-in-out; /* Smooth fade effect */
                        }
                    </style>

                    <table class="table table-borderless table-hover custom-selectable-table" id="customerRatings">
                        <thead>
                            <tr>
                                <th>TIME</th>
                                <th>CUSTOMER</th>
                                <th>MESSAGE</th>
                                <th>RATING</th>
                                <th>SENTIMENT</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="100%">{!! spinner() !!}</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Custom Database Pagination Sync Controllers -->
                <div class="custom-pagination">
                  <button class="btn btn-outline-primary btn-sm" onclick="navigatePage(-1)">Previous</button>
                  <span id="pageLabel">Page 1</span>
                  <button class="btn btn-outline-primary btn-sm" onclick="navigatePage(1)">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resolution modal template -->
    @php $ticket = optional(); @endphp
    @include('whatsapp.partial.resolution_modal')
@stop

@section('script')
<script>
    // 1. Initialize simple-datatables with client paging off
    let dataTableInstance = new simpleDatatables.DataTable("#customerRatings", {
        paging: false, // 👈 Stop client-side paging; backend handles it
        searchable: true,
        sortable: true
    });

    // 2. Global Pagination State
    let currentPage = 1;
    let currentLimit = 10;

    // 3. Main Data Orchestrator
    async function loadServerData() {
        // Calculate standard database offset
        const calculatedOffset = (currentPage - 1) * currentLimit;

        try {
            // Fetch specific slice from your DB
            const url = "{{ route('whatsapp.customer_rating_datatable') }}";
            const data = {
                limit: currentLimit,
                offset: calculatedOffset,
                status: $('#status').val(),
                optout: $('#optOut').val(),
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify(data)
            });

            const htmlRows = await response.text(); // 👈 Use .text() instead of .json()

            // 3. Properly cycle the datatable instance to prevent memory leaks
            if (dataTableInstance) {
                dataTableInstance.destroy();
            }

            // 4. Inject the raw HTML string into the empty table body container
            document.querySelector("#customerRatings tbody").innerHTML = htmlRows;

            // 5. Re-initialize the library so it can read and style the new HTML
            dataTableInstance = new simpleDatatables.DataTable("#customerRatings", {
                paging: false, // Keep disabled so it doesn't conflict with server pages
                searchable: true,
                sortable: true
            });           

            // Update UI indicator text
            document.getElementById("pageLabel").textContent = `Page ${currentPage}`;
        } catch (error) {
            console.error("Database data synchronization failed:", error);
        }
    }
    // 4. UI Interaction Handlers
    function updateLimit() {
        currentLimit = parseInt(document.getElementById("dbLimit").value);
        currentPage = 1; // Reset to start index to avoid out-of-bounds offsets
        loadServerData();
    }
    function navigatePage(direction) {
        if (currentPage + direction < 1) return; // Block negative offsets
        currentPage += direction;
        loadServerData();
    }

    // Initial Boot Lifecycle Load
    document.addEventListener("DOMContentLoaded", () => {
        loadServerData();
    });

    $(() => {
        $('#status,#optOut').change(function() { 
            loadServerData(); 
        }); 


        /**
         * Resolution Modal Logic
         * */ 
        $('#customerRatings').on('click', 'tbody tr', function() {
            const dataId = $(this).attr('data-id');

            // Fetch modal server-side
            let url = "{{ route('whatsapp.resolution_modal') }}";
            fetch(`${url}?customer_rating_id=${dataId}`)
                .then(resp => {
                    if (!resp.ok) {
                        return resp.json().then(({message}) => {
                            throw new Error(message);
                        });                        
                    }
                    return resp.text();
                })
                .then(data => {
                    $('#resolutionModal').modal('show');
                    const modalContent = $(data).find('.modal-content');
                    $('#resolutionModal .modal-content').html(modalContent.html());
                })
                .catch(error => console.log(error));

            // Fetch modal messages
            url = "{{ route('whatsapp.modal_messages') }}";
            fetch(`${url}?customer_rating_id=${dataId}`)
                .then(resp => {
                    if (!resp.ok) {
                        return resp.json().then(({message}) => {
                            throw new Error(message);
                        });                        
                    }
                    return resp.text();
                })
                .then(data => {
                    if ($('#resolutionModal').hasClass('show')) {
                        $('#resolutionModal #chatBody').html(data);
                    }
                })
                .catch(error => console.log(error));
        });

        // submit forms
        $('#resolutionModal')
        .on('submit', '#resolutionForm', function(e) {
            // POST resolution action
            e.preventDefault();
            const formData = new FormData($(this)[0]);
            fetch("{{ route('whatsapp.resolve_feedback') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                },
                body: formData,
            })
            .then(resp => {
                if (!resp.ok) {
                    return resp.json().then(({message}) => {
                        throw new Error(message);
                    });                        
                }
                return resp.json();
            })
            .then(({message, payload}) => {
                alert(message);
                const li = `<li><b>Resolved</b><br><span>${payload.resolved_at}</span></li>`;
                $('#resolutionModal .timeline li:last').remove();
                $('#resolutionModal .timeline').append(li);
            })
            .catch(error => {
                console.log(error);
                alert(error.statusText);
            });            
        })
        .on('submit', '#followUpForm', function(e) {
            // POST follow-up message
            e.preventDefault();
            const formData = new FormData($(this)[0]);
            fetch("{{ route('whatsapp.follow_up_message') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                },
                body: formData,
            })
            .then(resp => {
                if (!resp.ok) {
                    return resp.json().then(({message}) => {
                        throw new Error(message);
                    });                        
                }
                return resp.json();
            })
            .then(({message}) => {
                alert(message);
            })
            .catch(error => console.log(error));  
        });


        // reply window logic
        const lastMessageTime = new Date("{{ $ticket->comment_received_at }}");
        const optOut = {{ $ticket->is_opt_out ? 1 : 0 }};

        function checkReplyWindow(){
            const now = new Date();
            const hoursPassed = (now - lastMessageTime) / (1000 * 60 * 60);

            if(optOut === 1){
                $("#replyBox")
                    .prop("disabled", true)
                    .attr(
                        "placeholder",
                        "Customer has opted out of SMS communication."
                    );

                $("#sendReply").hide();
                $("#replyStatus").text("SMS communication blocked");
                return;
            }

            if(hoursPassed >= 24 || Number.isNaN(hoursPassed)){
                $("#replyBox")
                    .prop("disabled", true)
                    .attr(
                        "placeholder",
                        "The 24-hour standard reply window has expired."
                    );

                $("#sendReply").hide();
                $("#replyStatus").text("Reply window expired");
                return;
            }

            const remaining = 24 - hoursPassed;

            $("#replyStatus").text(Math.floor(remaining) + "h remaining to reply");
        }
        checkReplyWindow();

        setInterval(checkReplyWindow, 60000);
    });
</script>    
@stop
