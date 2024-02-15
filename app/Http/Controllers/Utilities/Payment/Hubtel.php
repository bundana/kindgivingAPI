<?php

namespace App\Http\Controllers\Utilities\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Hubtel extends Controller
{
 private $totalAmount; //Specifies the total amount expected to be paid for the Items being purchased.
 private $description; //describes to customers actions they can perform on the checkout page
 private $callbackUrl; //The URL registered to receive the final notification on the status of the payment.
 private $returnUrl; //The URL where the customer should return after payment.
 private $cancellationUrl; // The URL where the customer should return to when they cancel the payment.
 private $clientReference; //The client reference is a unique identifier for the transaction generated
 private $merchantAccountNumber; //The merchant account number is the account number of the merchant initiating the transaction.
 public function __construct($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $clientReference, $merchantAccountNumber = null)
 {
  $this->totalAmount = $totalAmount;
  $this->description = $description;
  $this->callbackUrl = $callbackUrl;
  $this->returnUrl = $returnUrl;
  $this->cancellationUrl = $cancellationUrl;
  $this->clientReference = $clientReference;
  $this->merchantAccountNumber = $merchantAccountNumber ?: env('HUBTEL_MECHANT_NUMBER', 2019424);
 }

 /**
  * Makes an API request to Paystack to verify a transaction.
  * @link https://paystack.com/docs/payments/verify-payments/ 
  */
 public function initiate()
 {
  $curl = curl_init();

  // Assuming $this->totalAmount, $this->description, etc. are properties of your class
  $data = [
   'totalAmount' => $this->totalAmount,
   'description' => $this->description,
   'callbackUrl' => $this->callbackUrl,
   'returnUrl' => $this->returnUrl,
   'cancellationUrl' => $this->cancellationUrl,
   'merchantAccountNumber' => $this->merchantAccountNumber,
   'clientReference' => $this->clientReference,
  ];

  $jsonData = json_encode($data);

  $credentials = env('HUBTEL_API_USERNAME', 'KZzq12r') . ':' . env('HUBTEL_API_PASSWORD', '7a80136175c444159f34362e4ca2fe96');
  $base64Credentials = base64_encode($credentials);

  curl_setopt_array($curl, array(
   CURLOPT_URL => 'https://payproxyapi.hubtel.com/items/initiate',
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_ENCODING => '',
   CURLOPT_MAXREDIRS => 10,
   CURLOPT_TIMEOUT => 0,
   CURLOPT_FOLLOWLOCATION => true,
   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
   CURLOPT_CUSTOMREQUEST => 'POST',
   CURLOPT_POSTFIELDS => $jsonData,
   CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Authorization: Basic ' . $base64Credentials
   ),
  ));

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
   // Handle cURL error
   return json_encode(['status' => false, 'error' => $err]);
  } else {
   // Decode the JSON response
   $response = json_decode($response, true);

   if (isset($response['responseCode']) && $response['responseCode'] == '0000' && isset($response['status']) && ($response['status'] == 'Success') || ($response['status'] == 'success')) {

    // Handle the API response for success
    $responseData = $response['data'];
    return json_encode(['status' => true, 'data' => $responseData]);
   } else {
    // Handle the API response for failure
    $errorData = isset($response['data']) ? $response['data'] : 'Unknown error';
    return json_encode(['status' => false, 'error' => $errorData]);
   }
  }
 }

 public function webhook(Request $request)
 {

 }
}
