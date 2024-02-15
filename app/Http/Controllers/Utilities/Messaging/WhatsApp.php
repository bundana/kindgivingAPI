<?php

namespace App\Http\Controllers\Utilities\Messaging;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatsApp extends Controller
{
 protected $apiToken;
 public $phone, $message;
 public function __construct()
 {
  $this->apiToken = env('WHATSAPP_API_TOKEN');
 }

 public static function to($phone)
 {
  $instance = new static();
  $instance->phone = $phone;

  return $instance;
 }

 public function message($message)
 {
  $this->message = $message;
  return $this;
 }

 public function send()
 {
  $url = 'https://graph.facebook.com/v17.0/171224716081368/messages';
  $headers = array(
   'Authorization: Bearer ' . $this->apiToken,
   'Content-Type: application/json'
  );
  $data = array(
   'messaging_product' => 'whatsapp',
   'recipient_type' => 'individual',
   'to' => $this->phone,
   'type' => 'text',
   'text' => [
    'preview_url' => false,
    'body' => $this->message
   ]
  );
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  // You may want to handle the response or return something based on success/failure
  return   $response;
 }

 /**
  * Sends bulk messages to multiple contacts using WhatsApp.
  *
  * Example usage:
  * ```
  * $contactsAndMessages = [
  *     'recipient1_phone_number' => 'message1',
  *     'recipient2_phone_number' => 'message2', 
  * ];
  *
  * $responses = WhatsApp::sendBulk($contactsAndMessages);
  * ```
  *
  * @param array $contactsAndMessages An associative array where the keys are the contacts and the values are the messages.
  * @return array An associative array where the keys are the contacts and the values are the responses from sending the messages.
  */
 public static function sendBulk($contactsAndMessages)
 {
  $responses = [];

  foreach ($contactsAndMessages as $contact => $message) {
   $instance = new static();
   $responses[$contact] = $instance->to($contact)->message($message)->send();
  }
  return $responses;
 }
}
