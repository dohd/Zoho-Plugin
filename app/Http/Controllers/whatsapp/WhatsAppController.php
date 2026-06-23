<?php

namespace App\Http\Controllers\whatsapp;

use App\Http\Controllers\Controller;
use App\Models\whatsapp\CustomerRating;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    protected $twilioClient;
    protected $from;

    public function __construct()
    {
        $this->twilioClient = new Client(
            env('TWILIO_API_KEY_SID'),
            env('TWILIO_API_KEY_SECRET'),
            env('TWILIO_ACCOUNT_SID')
        );
        $this->from = env('TWILIO_WHATSAPP_NUMBER');
    }

    public function overview()
    {
        return view('whatsapp.overview');
    }

    public function customerRating()
    {
        return view('whatsapp.customer_rating');
    }

    public function customerRatingDatatable()
    {
        $customerRatings = CustomerRating::latest()
            ->limit(request('limit'))
            ->offset(request('offset'))
            ->get();

        return view('whatsapp.partial.customer_rating_datatable', compact('customerRatings'));
    }


    public function messageLog()
    {
        return view('whatsapp.message_log');
    }

    public function messageLogDatatable()
    {
        $twilio = $this->twilioClient;

        $logs = collect();
        $errorMsg = '';

        try {
            $messages = $twilio->messages->stream([
                // 'from' => 'whatsapp:' . $this->from,
                'limit' => request('limit'),
            ]);

            foreach ($messages as $record) {
                $logs->add([
                    'sid'     => $record->sid,
                    'from'    => str_replace('whatsapp:', '', $record->from),
                    'to'      => str_replace('whatsapp:', '', $record->to),
                    'body'    => $record->body,
                    'status'  => $record->status,
                    'date'    => $record->dateSent ? $record->dateSent->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            $logs = $logs->take(request('limit'));

        } catch (TwilioException $e) {
            // 2. THIS IS A TWILIO API ERROR
            $twilioErrorCode = $e->getCode(); // Twilio-specific error code (e.g., 21211)
            $httpStatusCode  = $e->getStatusCode(); // HTTP code (e.g., 400, 401, 404)
            $errorMessage    = $e->getMessage(); // Plain text explanation

            Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMsg = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            Log::critical("Internal application error: " . $e->getMessage());
            $errorMsg = "A system error occurred! Please contact system admin.";
        }

        return view('whatsapp.partial.message_log_datatable', compact('logs', 'errorMsg'));
    }

    public function triggerRatingMessage($to)
    {
        $formattedTo = $this->formatToWhatsAppNumber($to);
        $formattedFrom = "whatsapp:{$this->from}";

        // The template SID from your Twilio Content Template Builder dashboard
        $contentSid = env('TWILIO_WHATSAPP_CUSTOMER_RATING_TEMPLATE_SID');
        $companyName = env('TWILIO_COMPANY_NAME');

        $twilio = $this->twilioClient;
        $message = $twilio->messages->create($formattedTo, [
            'from' => $formattedFrom,
            'contentSid' => $contentSid,
            'contentVariables' => json_encode([
                '1' => $companyName,
            ]),
        ]);

        return $message->sid;
    }

    public function sendFreeFormMessage($to, $body)
    {
        $formattedTo = $this->formatToWhatsAppNumber($to);
        $formattedFrom = "whatsapp:{$this->from}";

        $twilio = $this->twilioClient;
        $message = $twilio->messages->create($formattedTo, [
            'from' => $formattedFrom,
            'body' => $body,            
        ]);

        return $message->sid;
    }

    public function triggerOptOutMessage($to)
    {
        $formattedTo = $this->formatToWhatsAppNumber($to);
        $formattedFrom = "whatsapp:{$this->from}";

        // The template SID from your Twilio Content Template Builder dashboard
        $contentSid = env('TWILIO_WHATSAPP_CUSTOMER_OPTOUT_TEMPLATE_SID');

        $twilio = $this->twilioClient;
        $message = $twilio->messages->create($formattedTo, [
            'from' => $formattedFrom,
            'contentSid' => $contentSid,
        ]);

        return $message->sid;
    }

    public function triggerOptBackMessage($to)
    {
        $formattedTo = $this->formatToWhatsAppNumber($to);
        $formattedFrom = "whatsapp:{$this->from}";

        // The template SID from your Twilio Content Template Builder dashboard
        $contentSid = env('TWILIO_WHATSAPP_CUSTOMER_OPTBACK_TEMPLATE_SID');

        $twilio = $this->twilioClient;
        $message = $twilio->messages->create($formattedTo, [
            'from' => $formattedFrom,
            'contentSid' => $contentSid,
        ]);

        return $message->sid;
    }

    public function paymentReceiptNotice(Request $request)
    {
        $expectedKey = env('DELUGE_AUTH');
        $providedKey = $request->header('X-DELUGE-AUTH');
        if (!$providedKey || $providedKey !== $expectedKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or missing API Key.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required', 
            'invoice_id' => 'required', 
            'payment_received_id' => 'required', 
            'invoice_no' => 'required', 
            'payment_received_no' => 'required', 
            'customer_name' => 'required', 
            'phone_number' => 'required'
        ], [
            // 'customer_id.required' => 'Customer is required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors(); // This is a MessageBag
            // Get all errors as array
            $errorMessages = $errors->all();
            // Get specific field errors
            // $customerErrors = $errors->get('customer_id');
            return response()->json([
                'status' => 'error', 
                'message' => 'Validation failed! ' . implode(', ', $errorMessages),
                'errors' => $errors
            ], 422);
        }

        $input = $request->only(['customer_id', 'invoice_id', 'payment_received_id', 'invoice_no', 'payment_received_no', 'customer_name', 'phone_number']);

        try {
            // check if customer opted-out
            $optOutExists = CustomerRating::where('customer_id', $input['customer_id'])->where('is_opt_out', 1)->exists();
            if ($optOutExists) {
                return response()->json([
                    'message' => "{$input['customer_name']} has opted out of promo-messages!",
                ]);
            }

            $to = str_replace('whatsapp:', '', $this->formatToWhatsAppNumber(request('phone_number')));
            $customerRating = CustomerRating::create($input);

            // customer rating message
            $sid = $this->triggerRatingMessage($to);

            $customerRating->update([
                'twilio_from' => $this->from,
                'twilio_to' => $to,
                'phone_number' => $to,
                'last_message_sid' => $sid,
                'sent_at' => now(),
            ]);

            return response()->json([
                'customer_rating' => $customerRating
            ]);
        } catch (TwilioException $e) {
            // 2. THIS IS A TWILIO API ERROR
            $twilioErrorCode = $e->getCode(); // Twilio-specific error code (e.g., 21211)
            $httpStatusCode  = $e->getStatusCode(); // HTTP code (e.g., 400, 401, 404)
            $errorMessage    = $e->getMessage(); // Plain text explanation

            Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMessage = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
            return response()->json(['error' => $errorMessage], 500);
        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            Log::critical("Internal application error: " . $e->getMessage());
            $errorMessage = "A system error occurred! Please contact system admin.";
            return response()->json(['error' => $errorMessage], 500);
        }       
    }

    public function feedbackMessage(Request $request)
    {
        $input = $this->convertKeysToSnakeCase($request->all());
        
        try {
            $from = str_replace('whatsapp:', '',  strval($input['from']));
            $body = trim($input['body']);

            $customerRating = CustomerRating::where('phone_number', 'LIKE', '%'. $from .'%')
                // ->whereIn('rating_status', ['pending_rating', 'pending_comment'])
                ->latest()
                ->first(); 
            if (!$customerRating) trigger_error('Resource could not be found for phone-number: ' . $from);

            if ($customerRating && $customerRating->is_opt_out) {
                return response()->json(['message' => 'Feedback cannot be processed! Customer opted-out']);
            }

            // opt-out of promo
            if ($customerRating) {
                if (strtolower($body) === 'stop') {
                    $customerRating->update(['is_opt_out' => 1, 'opt_out_at' => now()]);

                    // check template window
                    $currentTime = Carbon::now();
                    $lastMsgTime = Carbon::parse($customerRating->sent_at);
                    if ($currentTime->diffInHours($lastMsgTime) < 24) {
                        $this->sendFreeFormMessage($from, 'Your request to opt-out of marketing communications has been processed, thank you for your time. Send "START" to opt-in.');
                    } else {
                        $this->triggerOptOutMessage($from);
                    }                    
                } elseif (strtolower($body) === 'start') {
                    $customerRating->update([
                        'is_opt_out' => 0, 
                        'is_opt_back' => 1,
                        'opt_back_at' => now(),
                    ]);

                    $this->triggerOptBackMessage($from);
                }

                return response()->json($customerRating);                
            }

            // process ratings and comments
            if ($customerRating && $customerRating->rating_status === 'pending_rating') {
                $options = ['Excellent', 'Good', 'Fair', 'Poor', 'Very Poor'];
                if (!in_array($body, $options)) {
                    $this->sendFreeFormMessage($from, "Please reply from the options provided");
                    return response()->json(['message' => 'Feedback cannot be processed! Invalid option'], 500);
                }

                // assign rating score
                $pos = array_search($body, $options) + 1;
                $score = count($options) + 1 - $pos;

                // assign sentiment
                $sentiment = $score >= 4? 'positive' : ($score === 3? 'neutral' : 'negative');

                $customerRating->update([
                    'sentiment' => $sentiment,
                    'rating_score' => $score,
                    'rating_status' => 'pending_comment',
                    'rating_received_at' => now(),
                ]);

                $this->sendFreeFormMessage($from, "Thank you. Please share one short comment about your experience.");
            } elseif ($customerRating && $customerRating->rating_status === 'pending_comment') {
                $customerRating->update([
                    'rating_comment' => $body,
                    'rating_status' => 'comment_received',
                    'comment_received_at' => now(),
                ]);

                $ratingScore = $customerRating->rating_score;
                if ($ratingScore >= 4) {
                    // "Thank you for the great feedback. Kindly leave us a public Google review here: {{google_review_link}}"
                    $this->sendFreeFormMessage($from, "Thank you for the great feedback.");
                    $customerRating->update(['rating_status' => 'google_review_requested']);
                } elseif ($ratingScore == 3) {
                    $this->sendFreeFormMessage($from, "Thank you for your honest feedback. We shall use it to improve our service.");
                    $customerRating->update(['rating_status' => 'closed']);
                } else {
                    $this->sendFreeFormMessage($from, "We are sorry your experience did not meet expectations. Your concern has been escalated and our team will contact you shortly.");
                    $customerRating->update(['rating_status' => 'complaint_created']);
                }
            }  
            
            return response()->json($customerRating);            
        } catch (TwilioException $e) {
            // 2. THIS IS A TWILIO API ERROR
            $twilioErrorCode = $e->getCode(); // Twilio-specific error code (e.g., 21211)
            $httpStatusCode  = $e->getStatusCode(); // HTTP code (e.g., 400, 401, 404)
            $errorMessage    = $e->getMessage(); // Plain text explanation

            Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMessage = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
            return response()->json(['error' => $errorMessage], 500);
        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            Log::critical("Internal application error: " . $e->getMessage());
            $errorMessage = "A system error occurred! Please contact system admin.";
            return response()->json(['error' => $errorMessage], 500);
        }        
    }



    public function convertKeysToSnakeCase(array $input): array
    {
        $snakeCased = [];

        foreach ($input as $key => $value) {
            // Recursively convert if the value is a nested array
            $actualValue = is_array($value) ? $this->convertKeysToSnakeCase($value) : $value;
            $snakeCased[Str::snake($key)] = $actualValue;
        }

        return $snakeCased;
    }

    public function formatToWhatsAppNumber(string $number): string
    {
        // Remove spaces, dashes, and plus signs
        $number = preg_replace('/[^0-9]/', '', $number);

        // Handle Kenyan format specifically (07 / 01 / 7 digit cases)
        if (str_starts_with($number, '0')) {
            // e.g. 0710xxxxxx → 254710xxxxxx
            $number = '254' . substr($number, 1);
        } elseif (str_starts_with($number, '7') || str_starts_with($number, '1')) {
            // e.g. 710xxxxxx → 254710xxxxxx
            $number = '254' . $number;
        }

        // Ensure it does not already contain country code duplication
        if (!str_starts_with($number, '254')) {
            $number = '254' . ltrim($number, '0');
        }

        return 'whatsapp:+' . $number;
    }

    public function usageWhatsappStats()
    {
        $twilio = $this->twilioClient;
        // Fetch pre-calculated usage stats for WhatsApp
        $usageRecords = $twilio->usage->records->read([
            "category" => "whatsapp-messages",
            "startDate" => new \DateTime("2026-06-01"),
            "endDate"   => new \DateTime("2026-06-15")
        ]);

        foreach ($usageRecords as $record) {
            // The "count" property here is generated instantly by Twilio
            echo "Total WhatsApp Messages Sent: " . $record->count;
        }
    }
}
