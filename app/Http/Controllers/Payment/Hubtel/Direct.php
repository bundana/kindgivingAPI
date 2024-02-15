<?php

namespace App\Http\Controllers\Payment\Hubtel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Direct extends Controller
{
    private const PAYMENT_USERNAME = 'KZzq12r';
    private const PAYMENT_API_KEY = '7a80136175c444159f34362e4ca2fe96';
    private const MERCHANT = 2019424;


    public function __invoke(Request $request, array $data)
    {
        // Get the JSON contents from the request
        $json = $request->getContent();

        // Decode the JSON data
        $data = json_decode($json, true);

        // Check if JSON data is empty
        if (!$data) {
            return "No data";
        }

        // Check if required fields are present in the JSON data
        $requiredFields = ["ClientReference", "CustomerName", "CustomerMsisdn", "CustomerEmail", "Channel", "Amount", "PrimaryCallbackUrl", "Description"];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return "Invalid data - Missing required field: $field";
            }
        }

        // Set up cURL for making a POST request to the remote API endpoint
        $curl = curl_init();
        $apiId = self::PAYMENT_USERNAME; // Your API ID
        $apiKey = self::PAYMENT_API_KEY; // Your API Key
        $merchant = self::MERCHANT; // Your merchant ID

        // Encode API ID and API Key to Base64 for authentication
        $authHeader = base64_encode($apiId . ':' . $apiKey);

        // Set cURL options for the POST request
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://rmp.hubtel.com/merchantaccount/merchants/$merchant/receive/mobilemoney",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data), // Convert data back to JSON for the request payload
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $authHeader // Include Basic Authorization header
            ],
        ]);

        // Execute the cURL request
        $response = curl_exec($curl);

        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return "cURL error: $error";
        }

        // Get HTTP status code
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the cURL session
        curl_close($curl);

        // Check HTTP status code and handle response accordingly
        if ($httpCode >= 200 && $httpCode < 300) {
            return $response;
        } else {
            return "Error: Unexpected HTTP status code $httpCode";
        }
    }
}
