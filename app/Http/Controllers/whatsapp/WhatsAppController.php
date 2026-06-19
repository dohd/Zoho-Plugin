<?php

namespace App\Http\Controllers\whatsapp;

use App\Http\Controllers\Controller;
use App\Models\whatsapp\CustomerRating;
use Illuminate\Http\Request;
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

            \Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMsg = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            \Log::critical("Internal application error: " . $e->getMessage());
            $errorMsg = "A system error occurred! Please contact system admin.";
        }

        return view('whatsapp.partial.message_log_datatable', compact('logs', 'errorMsg'));
    }

    public function triggerRatingMessage($to)
    {
        $formattedTo = $this->formatToWhatsAppNumber($to);
        $formattedFrom = "whatsapp:{$this->from}";

        // The template SID from your Twilio Content Template Builder dashboard
        $contentSid = env('TWILIO_WHATSAPP_TEMPLATE_SID');
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


    public function sendMessage($to, $body)
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


    public function paymentReceiptNotice(Request $request)
    {
        $input = $request->only(['customer_id', 'invoice_id', 'payment_received_id', 'invoice_no', 'payment_received_no', 'customer_name', 'phone_number']);

        try {
            $to = str_replace('whatsapp:', '', $this->formatToWhatsAppNumber(request('phone_number')));

            $customerRating = CustomerRating::create($input);
            $sid = $this->triggerRatingMessage($to);

            $customerRating->update([
                'twilio_from' => $this->from,
                'twilio_to' => $to,
                'phone_number' => $to,
                'last_message_sid' => $sid,
                'sent_at' => now(),
            ]);

            return response()->json([
                'message_sid' => $sid,
                'customer_rating' => $customerRating
            ]);

        } catch (TwilioException $e) {
            // 2. THIS IS A TWILIO API ERROR
            $twilioErrorCode = $e->getCode(); // Twilio-specific error code (e.g., 21211)
            $httpStatusCode  = $e->getStatusCode(); // HTTP code (e.g., 400, 401, 404)
            $errorMessage    = $e->getMessage(); // Plain text explanation

            \Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMsg = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
            return response()->json(['error' => $errorMessage], 500);

        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            \Log::critical("Internal application error: " . $e->getMessage());
            $errorMsg = "A system error occurred! Please contact system admin.";
            return response()->json(['error' => $errorMessage], 500);
        }       
    }

    public function feedbackMessage(Request $request)
    {
        $input = $this->convertKeysToSnakeCase($request->all());
        
        try {
            $from = str_replace('whatsapp:', '',  strval($input['from']));
            $customerRating = CustomerRating::where('phone_number', 'LIKE', '%'. $from .'%')
                ->whereIn('rating_status', ['pending_rating', 'pending_comment'])
                ->latest()
                ->first(); 

            $body = $input['body'];
            if ($customerRating && $customerRating->rating_status === 'pending_rating') {
                $options = ['Excellent', 'Good', 'Fair', 'Poor', 'Very Poor'];
                if (!in_array($body, $options)) {
                    $this->sendMessage($from, "Please reply from the options provided");
                }

                $pos = array_search($body, $options) + 1;
                $score = count($options) + 1 - $pos;

                $customerRating->update([
                    'rating_score' => $score,
                    'rating_status' => 'pending_comment',
                    'rating_received_at' => now(),
                ]);

                $this->sendMessage($from, "Thank you. Please share one short comment about your experience.");

            } elseif ($customerRating && $customerRating->rating_status === 'pending_comment') {
                $customerRating->update([
                    'rating_comment' => $body,
                    'rating_status' => 'comment_received',
                    'comment_received_at' => now(),
                ]);

                $ratingScore = $customerRating->rating_score;
                if ($ratingScore >= 4) {
                    $this->sendMessage($from, "Thank you for the great feedback");
                    $customerRating->update(['rating_status' => 'google_review_requested']);
                } elseif ($ratingScore == 3) {
                    $this->sendMessage($from, "Thank you for your honest feedback. We shall use it to improve our service.");
                    $customerRating->update(['rating_status' => 'closed']);
                } else {
                    $this->sendMessage($from, "We are sorry your experience did not meet expectations. Your concern has been escalated and our team will contact you shortly.");
                    $customerRating->update(['rating_status' => 'complaint_created']);
                }
            }   

            if (!$customerRating) {
                trigger_error('Resource could not be found for phone-number: ' . $from);
            }   
            
            return response()->json($customerRating);
        } catch (TwilioException $e) {
            // 2. THIS IS A TWILIO API ERROR
            $twilioErrorCode = $e->getCode(); // Twilio-specific error code (e.g., 21211)
            $httpStatusCode  = $e->getStatusCode(); // HTTP code (e.g., 400, 401, 404)
            $errorMessage    = $e->getMessage(); // Plain text explanation

            \Log::error("Twilio specific error occurred [Code {$twilioErrorCode}]: {$errorMessage}");
            $errorMsg = "Twilio Communication Error (Code {$twilioErrorCode}): {$errorMessage}";
            return response()->json(['error' => $errorMessage], 500);
        } catch (\Exception $e) {
            // 3. THIS IS A LARAVEL / PHP ERROR
            // (e.g., Database connection down, syntax error, out of memory)
            \Log::critical("Internal application error: " . $e->getMessage());
            $errorMsg = "A system error occurred! Please contact system admin.";
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
