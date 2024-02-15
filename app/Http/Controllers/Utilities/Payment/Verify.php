<?php

namespace App\Http\Controllers\Utilities\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Verify extends Controller
{
    private $reference;

    public function __construct($reference)
    {
        $this->reference = $reference;
    }

    /**
     * Makes an API request to Paystack to verify a transaction.
     * @link https://paystack.com/docs/payments/verify-payments/ 
     */
    private function makeApiRequestViaPaystack()
    {

        $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($this->reference);
        $headers = [
            "Authorization: Bearer " . env('PAYSTACK_SECRET_KEY'),
            "Cache-Control: no-cache",
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $response = json_decode($response, true);

        curl_close($curl);

        if ($err) {
            // Handle cURL error 
            return json_encode(['status' => false, 'error' => $err]);
        } else {
            if (isset($response['status']) && $response['status'] == true) {
                // Handle the API response 
                return json_encode(['status' => true, 'data' => $response['data']]);
            } else {
                return json_encode(['status' => false, 'error' => $response['message']]);
            }
        }
    }

    /**
     * Verify transactions after payments using Paystack's verify API.
     * @link https://paystack.com/docs/payments/verify-payments/ 
     */
    public function verifyTransaction()
    {
        return $this->makeApiRequestViaPaystack();
    }

    
}
