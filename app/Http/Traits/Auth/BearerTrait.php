<?php

namespace App\Http\Traits\Auth;

use Illuminate\Http\Request;

trait BearerTrait
{
    // protected const BearerToken = '6Lfz5mMpAAAAAAA4';

    public function __invoke(Request $request)
    {
        return self::verifyToken($request);
    }

    private static function verifyToken(Request $request)
    {
        $bearerToken = '6Lfz5mMpAAAAAAA4';
        
        // Extract the token from the Authorization header
        $token = $request->bearerToken();

        // If no token is provided, return an error response
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'The request was not authorized', 'errorCode' => 401], 401);
        }

        // Decode the token
        $decodedToken = base64_decode($token);

        // Verify the decoded token against the expected value
        $secretUrlShortenerKey = $bearerToken;
        if ($token !== $secretUrlShortenerKey) {
            return response()->json(['success' => false, 'message' => 'Invalid Bearer Token', 'errorCode' => 401], 401);
        }

        // Token is valid, return it for further processing
        return $decodedToken;
    }
}
