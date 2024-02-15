<?php

use App\Http\Controllers\Payment\Hubtel\Callback;
use App\Http\Controllers\UrlGenerator;
use App\Http\Controllers\Utilities\USSDController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{UsersController};
use App\Http\Controllers\Api\Campaigns\Campaign;
use App\Http\Controllers\Api\Campaigns\Donation;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/{id}', [UrlGenerator::class, 'show'])->name('show-url'); 
// Route::get('/', function (Request $request) {
//   return redirect()->away('https://kindgiving.org');
// })->name('home');

Route::prefix('v1')->name('v1.')->group(function () {
  Route::match(['get', 'post'], '/payment/hubtel-callback', [Callback::class, 'onlineCheckoutWebhook'])->name('online-checkout-webhook');
  Route::match(['get', 'post'], '/payment/hubtel-direct-callback', [Callback::class, 'directPaymentPromptWebhook'])->name('direct-checkout-webhook');

  Route::match(['get', 'post'], '/payment/hubtel-ussd-callback', [Callback::class, 'ussdPaymentPromptWebhook'])->name('direct-checkout-webhook');

  Route::post('/ussd', [USSDController::class, 'handleUssd'])->name('handle-ussd');

  // Route::get('users', [UsersController::class, 'index']);
  // Route::post('users', [UsersController::class, 'store']);
  // Route::put('users/{user}', [UsersController::class, 'update']);
  // Route::delete('users/{user}', [UsersController::class, 'destroy']);


  Route::get('users/profile', [UsersController::class, 'show']);
  Route::get('campaigns/info', [Campaign::class, 'show']);
  Route::get('campaigns/donations/single', [Donation::class, 'show']);
  Route::post('campaigns/donations/create', [Donation::class, 'store']);

});


