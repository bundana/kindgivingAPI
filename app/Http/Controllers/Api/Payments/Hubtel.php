<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Auth\{BearerTrait, RequestMethod};

class Hubtel extends Controller
{
    use BearerTrait, RequestMethod;
    private const VERSION = 1;
    private const MERCHANT_ID = 2019424;
    private const USERNAME = "KZzq12r";
    private const PASSWORD = "7a80136175c444159f34362e4ca2fe96";

    public function _encodeAuthHeader()
    {
        return base64_encode(self::USERNAME . ':' . self::PASSWORD);
    }
    public function directPaymentPromt(Request $request)
    {
        $requestMethod = $this->post($request);
        if ($requestMethod instanceof \Illuminate\Http\JsonResponse) {
            return $requestMethod;
        }
        $authHeader = $this->_encodeAuthHeader(); //API Keys to Base64 for authentication

        // Validation rules
        $rules = [
            'payee_name' => ['required', 'string'],
            'msisdn' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'channel' => ['required', 'string', 'in:mtn,vodafone,airtel-tigo'],
            'amount' => ['required', 'numeric'],
            'callback_url' => ['required', 'string'],
            'description' => ['required', 'string'],
            'reference' => ['required', 'string'],
        ];

        // Validation custom error messages
        $messages = [
            'payee_name.string' => 'The payee name must be a string.',
            'payee_name.required' => 'The payee name is required',
            'msisdn.string' => 'The MSISDN must be a string.',
            'msisdn.required' => 'The MSISDN is required',
            'email.string' => 'The email must be a string.',
            'channel.string' => 'The channel must be a string.',
            'channel.required' => 'The channel is required',
            'channel.in' => 'The channel must be either mtn, vodafone, or airtel-tigo',
            'amount.numeric' => 'The amount must be a number.',
            'amount.required' => 'The amount is required',
            'callback_url.string' => 'The callback URL must be a string.',
            'callback_url.required' => 'The callback URL is required',
            'description.string' => 'The description must be a string.',
            'description.required' => 'The description is required',
            'reference.string' => 'The reference must be a string.',
            'reference.required' => 'The reference is required',
        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }
        $paymentChannel = $request->channel;

        switch ($paymentChannel) {
            case 'mtn':
                $paymentChannel = 'mtn-gh';
                break;
            case 'vodafone':
                $paymentChannel = 'vodafone-gh';
                break;
            case 'airtel-tigo':
                $paymentChannel = 'tigo-gh';
                break;
        }
        $paymentData = [
            "CustomerName" => $request->payee_name,
            "CustomerMsisdn" => $request->msisdn,
            "CustomerEmail" => $request->email,
            "Channel" => $paymentChannel,
            "Amount" => $request->amount,
            "PrimaryCallbackUrl" => $request->callback_url,
            "Description" => $request->description,
            "ClientReference" => $request->reference
        ];

        try {
            // Make a POST request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $authHeader // Include Basic Authorization header
            ])->post('https://rmp.hubtel.com/merchantaccount/merchants/2019424/receive/mobilemoney', $paymentData);
            // Output the response
            return $response->body();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on Hubtels\'s end.', 'errorCode' => 500], 500);
        }
    }
 
}
