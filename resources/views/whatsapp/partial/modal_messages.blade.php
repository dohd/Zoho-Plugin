@if ($messages->isNotEmpty())
    @foreach($messages as $message)
        @if($message->direction == 'outbound')
            <div class="d-flex justify-content-end mb-3">
                <div class="bg-primary text-white rounded p-3"  style="max-width:75%;">                                      
                    {{ $message->body }}
                    <div class="small opacity-75 mt-2">
                        {{ date('H:i d, M Y', strtotime($message->date)) }}
                    </div>
                </div>
            </div>
        @else
            <div class="d-flex justify-content-start mb-3">
                <div class="bg-light rounded p-3" style="max-width:75%;">                                       
                    {{ $message->body }}
                    <div class="small text-muted mt-2">
                        {{ date('H:i d, M Y', strtotime($message->date)) }}
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@else 
    <div class="d-flex justify-content-center">
        <span class="text-danger">Messages could not be found!</span>
    </div>
@endif