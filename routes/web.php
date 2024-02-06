<?php

use App\Http\Controllers\UrlGenerator;
use Illuminate\Support\Facades\Route;

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

Route::get('/{id}', [UrlGenerator::class, 'show'])->name('show-url');
Route::get('', [UrlGenerator::class, 'show'])->name('show');
Route::get('/dg', [UrlGenerator::class, 'show'])->name('show');

