<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\USSDSESSION;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class USSDController extends Controller
{
    public $campaign_id;


    public function handleUssd(Request $request)
    {
        // Get the JSON contents
        $json = $request->getContent();

        // Decode the JSON data
        $data = json_decode($json, true);

        // Log the received JSON data
        // Log::info("Received JSON data: " . print_r($data, true));


        $this->campaign_id = 'S1R6VORk94bB';
        // create a new session
        $session = USSDSESSION::updateOrInsert(
            [
                'campaign_id' => $this->campaign_id,
                'session_id' => $data['SessionId']
            ],
            [
                'mobile' => $data['Mobile'],
                'platform' => $data['Platform'],
                'message' => $data['Message'],
                'service_code' => $data['ServiceCode'],
                'operator' => $data['Operator'],
                'donation_amount' => 0,
                'type' => $data['Type']
            ]
        );
        
        // Extract relevant information from the received data
        $type = strtoupper($data['Type']); // Convert type to uppercase for consistency
        $sessionId = $data['SessionId'];
        $mobile = $data['Mobile'];
        $platform = strtoupper($data['Platform']);
        $message = strtoupper($data['Message']);

        // Initialize session variables if not set
        if (!isset($_SESSION['donation_flow'])) {
            $_SESSION['donation_flow'] = array(
                'menu_level' => 0, // 0: Main menu, 1: Make Donation
                'amount' => 0
            );
        }

        // Check the type of request
        if ($type === 'INITIATION') {
            // User initiated the session, respond with the main menu
            $response = array(
                'SessionId' => $sessionId,
                'Type' => 'response',
                'Message' => "Welcome to the Kind Giving Donation Service!\n\n1). Make Donation\n2). Get Help",
                'Label' => 'Main Menu',
                'DataType' => 'display',
                'FieldType' => 'text',
                'Sequence' => 1,
                'ClientState' => 100
            );
        } elseif ($type === 'RESPONSE') {
            // User responded to a menu, process the response based on the current menu level
            $menuLevel = $_SESSION['donation_flow']['menu_level'];

            switch ($menuLevel) {
                case 0: // Main Menu
                    if ($message === '1') {
                        // User selected "Make Donation", prompt for amount
                        $_SESSION['donation_flow']['menu_level'] = 1;
                        $response = array(
                            'SessionId' => $sessionId,
                            'Type' => 'response',
                            'Message' => "Please enter the donation amount\neg. 1 or 1.5 \n\n#. Back",
                            'Label' => 'Enter Amount',
                            'DataType' => 'input',
                            'FieldType' => 'number',
                            'Sequence' => 1,
                            'ClientState' => 100
                        );
                    } elseif ($message === '2') {
                        // User selected "Get Help", provide help information (you can customize this)
                        $response = array(
                            'SessionId' => $sessionId,
                            'Type' => 'release',
                            'Message' => "If you need help, please contact our support team.",
                            'Label' => 'Help Information',
                            'DataType' => 'display',
                            'FieldType' => 'text',
                            'Sequence' => 1,
                            'ClientState' => 100
                        );
                    } else {
                        // Invalid selection, show main menu again
                        $response = array(
                            'SessionId' => $sessionId,
                            'Type' => 'response',
                            'Message' => "Invalid selection. Please choose a valid option:\n\n1). Make Donation\n2). Get Help",
                            'Label' => 'Main Menu',
                            'DataType' => 'display',
                            'FieldType' => 'text',
                            'Sequence' => 1,
                            'ClientState' => 100
                        );
                    }
                    break;

                case 1: // Make Donation - Enter Amount
                    // Validate and process the donation amount
                    if ($message === '#') {
                        // User wants to go back to the main menu
                        $_SESSION['donation_flow']['menu_level'] = 0;
                        $response = array(
                            'SessionId' => $sessionId,
                            'Type' => 'response',
                            'Message' => "Welcome to the Kind Giving Donation Service!\n\n1). Make Donation\n2). Get Help",
                            'Label' => 'Main Menu',
                            'DataType' => 'display',
                            'FieldType' => 'text',
                            'Sequence' => 1,
                            'ClientState' => 100
                        );
                    } else {
                        $donationAmount = floatval($message);
                        if ($donationAmount > 0) {
                            // Generate a reference and display a thank you message
                            $reference = Str::random(12);
                            $response = array(
                                'SessionId' => $sessionId,
                                'Type' => 'AddToCart',
                                'Message' => "Thanks for supporting Kind Giving. Please wait for the payment prompt. \nReference: $reference",
                                'Label' => 'Donation Confirmation',
                                'DataType' => 'display',
                                'FieldType' => 'text',
                                'Item' => [
                                    'ItemName' => "Donation of $donationAmount",
                                    'Qty' => 1,
                                    'Price' => $donationAmount ?: 1
                                ],
                                'Sequence' => 1,
                                'ClientState' => 100
                            );
                        } else {
                            // Invalid amount, prompt user to enter a valid amount
                            $response = array(
                                'SessionId' => $sessionId,
                                'Type' => 'response',
                                'Message' => "Invalid amount. Please enter a valid donation amount or enter \n\n#). Back",
                                'Label' => 'Enter Amount',
                                'DataType' => 'input',
                                'FieldType' => 'number',
                                'Sequence' => 1,
                                'ClientState' => 100
                            );
                        }
                        // Reset menu level after processing the donation
                        $_SESSION['donation_flow']['menu_level'] = 0;
                    }
                    break;

                default:
                    // Invalid menu level, show main menu again
                    $response = array(
                        'SessionId' => $sessionId,
                        'Type' => 'response',
                        'Message' => "Invalid menu level. Please choose a valid option:\n\n1. Make Donation\n2). Get Help",
                        'Label' => 'Main Menu',
                        'DataType' => 'display',
                        'FieldType' => 'text',
                        'Sequence' => 1,
                        'ClientState' => 100
                    );
                    break;
            }
        } else {
            // Other types of requests are not supported in this example, show an error
            $response = array(
                'SessionId' => $sessionId,
                'Type' => 'response',
                'Message' => "Error: Unsupported request type.",
                'Label' => 'Error',
                'DataType' => 'display',
                'FieldType' => 'text',
                'Sequence' => 1,
                'ClientState' => 100
            );
        }
        // Encode the response as JSON and send it back
        return response()->json($response);
    }

}
