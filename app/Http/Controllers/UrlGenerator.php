<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Auth\Bearer;
use App\Http\Controllers\Controller;
use App\Http\Traits\Auth\BearerTrait;
use App\Models\Campaigns\Campaign as CampaignsCampaign;
use App\Models\Campaigns\Donations;
use App\Models\User;
use App\Models\Campaigns\{Campaign as CampaignModel, CampaignAgent};

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use AshAllenDesign\ShortURL\Classes\Builder;
use AshAllenDesign\ShortURL\Models\ShortURL;

class UrlGenerator extends Controller
{
    use BearerTrait;
    public $shortURL = '';

    private $longURL = '';

    public $customKey;

    public $activateAt;

    public $deactivateAt;

    public function newUrl(Request $request)
    {
        $method = $request->method();
 
        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => "The {$method} request method not allowed", 'errorCode' => 401], 401);
        }
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
        $data = [
            'data' => [
                'short_url' => $this->shortURL,
                'long_url' => $this->longURL,
            ]
        ];
        // Return the generated short URL
        return response()->json(['success' => true, 'message' => 'Url shorten sucessfully', 'data' => $data], 200);
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
            // Return an error response with a meaningful message and the exception details
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on KindGiving\'s end.' . $e, 'errorCode' => 500], 500);
        }
    }

    public function show(Request $request)
    {
        $url = $request->id;

        $shortURL = ShortURL::where('url_key', $url)->first();
        if (!$shortURL) {
            redirect()->away('https://kindgiving.org');
        }
    }
}
