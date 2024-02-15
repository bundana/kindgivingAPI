<?php

namespace App\Http\Controllers\Utilities\Messaging;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SMS extends Controller
{
    private $api_key;
    private $sender_key;
    private $api_url;
    public $recipientPhone;
    public $sms_content;
    public $recipientName;
    public $message_type;

    public function __construct($recipientPhone, $smsContent, $recipientName = null, $message_type = null)
    {
        $this->recipientPhone = $recipientPhone;
        $this->sms_content = $smsContent;
        $this->recipientName = $recipientName;
        $this->message_type = $message_type;
        $this->api_key  = env('MNOTIFY_API_KEY', '3IYTu5x94SbfF3R8tC6nMLkWd');
        $this->sender_key = env('MNOTIFY_SENDER_ID', 'BundanaTech');
        $this->api_url = env('MNOTIFY_API_URL', 'https://apps.mnotify.net/smsapi');
    }

    private function validatePhoneNumber($phone)
    {
        // Regular expression pattern for Ghanaian phone numbers
        $pattern = '/^(020|024|054|055|056|057|059|027)[0-9]{7}$/';
        // Check if the phone number matches the pattern
        return preg_match($pattern, $phone) === 1;
    }

    private function makeApiRequest()
    {
        // Validate the phone number format if needed
        // Send SMS to user
        $recipient = $this->recipientPhone;
        $message = $this->sms_content;
        // Build the API request URL
        $query_params = [
            'key' => $this->api_key,
            'to' => $recipient,
            'msg' => $message,
            'sender_id' => $this->sender_key,
        ];
        $request_url = $this->api_url . '?' . http_build_query($query_params);

        // Make the API request using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $sms_response = curl_exec($ch);
        curl_close($ch);

        // Check if the API request was successful
        if ($sms_response === false) {
            http_response_code(500);
        }

        // Parse the API response
        $response_obj = json_decode($sms_response);
        $sms_delivery_status = ($response_obj->status ?? null === 'success') ? 'Sent' : 'Failed';
        $sms_error_message = ($sms_delivery_status === 'Failed') ? $response_obj->message ?? null : '';

        // // Insert SMS delivery status and information into the database using Eloquent
        // DB::table('messages')->insert([
        //     'sender_name' => WEB_TITLE,
        //     'sender_id' => '0542345921',
        //     'recipient_name' => $this->recipientName,
        //     'recipient_email' => $this->recipientPhone,
        //     'message_type' => $this->message_type,
        //     'sms_content' => $message,
        //     'sms_delivery_status' => $sms_delivery_status,
        //     'date_sent' => now(),
        // ]);
    }

    public function singleSendSMS()
    {
        // Validate the phone number format
        if (!$this->validatePhoneNumber($this->recipientPhone)) {
            // Invalid phone number format
        } else {
            $this->makeApiRequest();
        }
    }
}
