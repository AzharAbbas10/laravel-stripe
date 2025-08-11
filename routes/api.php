<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StripeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::controller(AuthController::class)->group(function(){
    Route::post('login','login');
    Route::post('register','register');
});

Route::controller(StripeController::class)->group(function(){
    Route::post('webhook/stripe','handle');
});
