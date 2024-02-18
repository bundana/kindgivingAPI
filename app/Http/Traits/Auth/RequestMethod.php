<?php

namespace App\Http\Traits\Auth;

use Illuminate\Http\Request;

trait RequestMethod
{ 

 private static function post(Request $request)
 {
  $method = $request->method();
  if (!$request->isMethod('post')) {
   return response()->json(['success' => false, 'message' => "This {$method} HTTPS method is not supported for this endpoint", 'errorCode' => 401], 401);
  }
 }

 private static function get(Request $request)
 {
  $method = $request->method();
  if (!$request->isMethod('get')) {
   return response()->json(['success' => false, 'message' => "This {$method} HTTPS method is not supported for this endpoint", 'errorCode' => 401], 401);
  }
 }
}
