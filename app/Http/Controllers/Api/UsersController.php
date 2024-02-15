<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Auth\Bearer;
use App\Http\Controllers\Controller;
use App\Http\Traits\Auth\BearerTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    use BearerTrait;
    private const VERSION = 1;

    public function index()
    {
        return User::all();
    }

    public function store(Request $request)
    {
        $user = User::create($request->all());

        return response()->json($user, 201);
    }

    public function show(Request $request)
    {
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        // Validation rules
        $rules = [
            'phone' => ['required_without:user_id', 'string'],
            'user_id' => ['required_without:phone', 'integer'],
        ];

        // Validation custom error messages
        $messages = [
            'phone.required_without' => 'Either phone number or user ID is required.',
            'user_id.required_without' => 'Either phone number or user ID is required.',
            'phone.string' => 'The phone number must be a string.',
            'user_id.integer' => 'The user ID must be an integer.',
        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }

        try {
               // Fetch user info based on user_id
               $user = User::select('name as full_name', 'email', 'phone_number as phone', 'avatar')
               ->where('phone_number', $request->phone)
               ->orWhere('user_id', $request->phone)
               ->first();


            // Check if user exists
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found', 'errorCode' => 404], 404);
            }

            // Return user info
            return response()->json(['success' => true, 'message' => 'User retrieved successfully', 'data' => $user]);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on KindGiving\'s end.', 'errorCode' => 500], 500);
        }
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->all());

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(null, 204);
    }


}
