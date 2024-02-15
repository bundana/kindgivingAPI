<?php

namespace App\Http\Controllers\Utilities\Payment;

use App\Http\Controllers\Utilities\Payment\Hubtel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiPayment extends Controller
{
 public function pay(Request $request)
 {
  // Retrieve request parameters
  $totalAmount = $request->input('totalAmount');
  $description = $request->input('description');
  $callbackUrl = $request->input('callbackUrl');
  $returnUrl = $request->input('returnUrl');
  $cancellationUrl = $request->input('cancellationUrl');
  $clientReference = $request->input('clientReference');

  // Instantiate the Hubtel class with your parameters
  $hubtel = new Hubtel($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $clientReference);

  // Call the initiate method to make the API request
  $response = $hubtel->initiate();

  // Decode the JSON response
  $responseArray = json_decode($response, true);

  // Check the status and handle the response accordingly
  if ($responseArray['status']) {
   // Success case
   $responseData = $responseArray['data']['data'];
   $clientReference = $responseData['clientReference'];
   $checkoutUrl = $responseData['checkoutUrl'];

   // Now you can use $clientReference and $checkoutUrl as needed
   return response()->json([
    'success' => true,
    'clientReference' => $clientReference,
    'checkoutUrl' => $checkoutUrl,
   ]);
  } else {
   // Error case
   $errorData = isset($responseArray['error']) ? $responseArray['error'] : 'Unknown error';
   $errorMessage = isset($errorData[0]['errorMessage']) ? $errorData[0]['errorMessage'] : 'Unknown error';
   return response()->json([
    'success' => false,
    'error' => $errorMessage,
   ]);
  }
 }
}
