<?php

namespace App\Http\Controllers;

use AshAllenDesign\ShortURL\Classes\Builder;
use AshAllenDesign\ShortURL\Models\ShortURL;
use Illuminate\Http\Request;

class UrlGenerator extends Controller
{
    public $shortURL = '';

    private $longURL = '';

    public $customKey;

    public $activateAt;

    public $deactivateAt;

    private const URLSHORTENER_SECRET_KEY = '6Lfz5mMpAAAAAAAA';

    public function newUrl(Request $request)
    {
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        $this->longURL = $request->url;
        $this->customKey = $request->customKey;
        $this->activateAt = $request->deactivateAt;
        $this->deactivateAt = $request->deactivateAt;

        // Generate new URL
        $this->shortURL = $this->generateNewUrl();

        // Return the generated short URL
        return response()->json(['response' => 'success', 'short_url' => $this->shortURL]);
    }

    private function verifyToken(Request $request)
    {
        // Extract the token from the Authorization header
        $token = $request->bearerToken();

        // If no token is provided, return an error response
        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Decode the token
        $decodedToken = base64_decode($token);

        // Verify the decoded token against the expected value
        $secretUrlShortenerKey = self::URLSHORTENER_SECRET_KEY;
        if ($token !== $secretUrlShortenerKey) {
            return response()->json(['error' => 'Invalid Bearer Token'], 401);
        }

        // Token is valid, return it for further processing
        return $decodedToken;
    }

    private function generateNewUrl()
    {
        $builder = new Builder();
        try {
            if ($this->activateAt) {
                $builder = $builder->activateAt($this->activateAt);
            }
            if ($this->deactivateAt) {
                $builder = $builder->deactivateAt($this->activateAt);
            }
            if ($this->customKey) {
                $builder = $builder->generateKeyUsing($this->customKey);
            }

            $shortURLObject = $builder->destinationUrl($this->longURL)->make();
            $this->shortURL = $shortURLObject->default_short_url;

            return $this->shortURL;
        } catch (\Exception $e) {
            // Log the exception for debugging purposes
            // \Log::error($e);
            // Return an error response with a meaningful message and the exception details
            return response()->json(['response' => 'error', 'error' => 'Failed to generate short URL', 'exception' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request)
    {
        $url = $request->id;

        $shortURL = ShortURL::where('url_key', $url)->first();
        if (! $shortURL) {
            redirect()->away('https://kindgiving.org');
        }
    }
}
