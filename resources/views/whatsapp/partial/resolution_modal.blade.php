<div class="modal fade" id="resolutionModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <style>
        .timeline {
          list-style:none;
          padding-left:15px;
        }

        .timeline li {
          padding:10px;
          border-left:2px solid #0d6efd;
          position:relative;
        }

        .timeline li:before {
          content:"";
          width:10px;
          height:10px;
          background:#0d6efd;
          border-radius:50%;
          position:absolute;
          left:-6px;
          top:15px;
        }

        #chatBody {
          background:#fafafa;
        }
      </style>

      <!-- Header -->
      <div class="modal-header sticky-top bg-white">
        <div>
          <h5 class="mb-1 fw-bold">{{ $ticket->customer_name }}</h5>
          <small class="fw-bold"><i class="bi bi-telephone-fill"></i> {{ $ticket->phone_number }}</small>
        </div>

        @if ($ticket->sentiment)
        <span class="badge ms-3
          @if($ticket->sentiment == 'positive') bg-success
          @elseif($ticket->sentiment == 'negative') bg-danger
          @else bg-secondary
          @endif
          ">
          @if($ticket->sentiment == 'positive') <i class="bi bi-emoji-smile"></i>
          @elseif($ticket->sentiment == 'negative') <i class="bi bi-emoji-frown"></i>
          @else <i class="bi bi-emoji-neutral"></i>
          @endif
          {{ $ticket->sentiment }}
        </span>
        @endif

        <button class="btn-close ms-auto" data-bs-dismiss="modal"></button>                        
      </div>

      <!-- Body -->
      <div class="modal-body p-0">
          <div class="row g-0" style="height:75vh;">
              <!-- Left Column -->
              <div class="col-md-3 border-end bg-light p-3">

                  <h6 class="fw-bold">Customer Context</h6>

                  <div class="card mb-3">
                    <i class="bi bi-copy copy-btn" data-value="{{ $ticket->invoice_no }}"></i>
                      <div class="card-body p-3">
                          <small>Invoice No</small>
                          <div><b>{{ $ticket->invoice_no }}</b> <i class="bi bi-clipboard float-end" data-value="{{ $ticket->invoice_no }}"></i></div>
                          <hr>
                          <small>Payment Received No</small>
                          <div><b>{{ $ticket->payment_received_no }}</b> <i class="bi bi-clipboard float-end" data-value="{{ $ticket->payment_received_no }}"></i></div>
                      </div>
                  </div>

                  <h6 class="fw-bold">Status Timeline</h6>

                  <ul class="timeline small">
                      <li><b>Sent</b><br><span>Survey sent to customer</span><br><span>{{ date('M d, Y H:i', strtotime($ticket->sent_at)) }}</span> </li>
                      @if ($ticket->rating_received_at)
                      <li><b>Received</b><br><span>Customer responded</span><br><span>{{ date('M d, Y H:i', strtotime($ticket->rating_received_at)) }}</span></li>
                      @endif
                      @if ($ticket->comment_received_at)
                      <li><b>Escalated</b><br><span>Marked for agent review</span><br><span>{{ date('M d, Y H:i', strtotime($ticket->comment_received_at)) }}</span></li>
                      @endif
                      @if ($ticket->resolved_at)
                        <li><b>Resolved</b><br><span>{{ date('M d, Y H:i', strtotime($ticket->resolved_at)) }}</span></li>
                      @else
                        <li><b>Closed</b><br><span>Pending Resolution</span></li>
                      @endif
                  </ul>

                  @if($ticket->is_opt_out)
                    <div class="alert alert-danger mt-4">
                      <strong>SMS Disabled</strong><br>
                      Customer opted out of SMS communication.
                    </div>
                  @endif
              </div>

              <!-- Middle Column -->
              <div class="col-md-6 d-flex flex-column">
                  <!-- Chat Messages -->
                  <div id="chatBody" class="flex-grow-1 overflow-auto p-3">
                      {!! spinner() !!}
                  </div>

                  <!-- Technical Details -->
                  <div class="border-top">
                      <button class="btn btn-sm w-100 text-start" data-bs-toggle="collapse" data-bs-target="#technicalInfo">
                          <i class="bi bi-gear"></i> Technical Details                          
                          <i class="bi bi-caret-up float-end"></i>
                      </button>

                      <div class="collapse px-3" id="technicalInfo">
                          <small class="text-muted">
                              <b>SID:</b> {{ $ticket->last_message_sid }}<br>
                              <b>From:</b> {{ $ticket->twilio_from }}<br>
                              <b>To:</b> {{ $ticket->twilio_to }}
                          </small>
                      </div>
                  </div>

                  <!-- Reply Composer -->
                  <form id="followUpForm">
                    @csrf
                    <input type="hidden" name="customer_rating_id" value="{{ $ticket->id }}">
                    <div class="border-top bg-white p-3">
                        <div class="input-group">
                            <textarea id="replyBox" class="form-control" rows="2" name="message" placeholder="Type a follow-up message..." required></textarea>
                            <button type="submit" id="sendReply" class="btn btn-primary">
                              <i class="bi bi-send"></i> Send
                            </button>
                        </div>
                        <small id="replyStatus" class="text-success"></small>
                    </div>                    
                  </form>
              </div>

              <!-- Right Column -->
              <div class="col-md-3 border-start p-3">
                  <h6 class="fw-bold">Resolution & Actions</h6>
                  <div class="text-center mb-4">
                      <h2>{{ $ticket->rating_score ?? 0 }}/4</h2>
                      <div class="text-warning fs-4">
                          @for($i = 1; $i <= 4; $i++)
                              {{ $i <= $ticket->rating_score ? '★' : '☆' }}
                          @endfor
                      </div>
                  </div>

                  <form id="resolutionForm">
                      @csrf
                      <input type="hidden" name="customer_rating_id" value="{{ $ticket->id }}">
                      <label class="fw-bold">Resolution Action</label>
                      <textarea class="form-control mt-2" rows="8" name="resolution_action" id="resolutionAction">{{ $ticket->resolution_action }}</textarea>
                      <button type="submit" class="btn btn-success w-100 mt-4">
                        <i class="bi bi-check-circle"></i> Mark as Resolved
                      </button>
                      <hr>
                      <a href="#" class="small d-block text-center mt-3">
                        <i class="bi bi-file-earmark-arrow-up"></i> Create Internal Complaint Record
                      </a>
                  </form>
              </div>
          </div>
      </div>
    </div>
  </div>
</div>