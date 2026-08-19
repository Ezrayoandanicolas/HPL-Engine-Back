<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'BackendNexusEngine API is running'
    ]);
});

// GGR Seamless API (inbound callbacks from GGR platform)
Route::post('/gold_api', [App\Http\Controllers\GoldApiController::class, 'handle']);

// DC Seamless Wallet API (operator level, inbound from DGC)
Route::post('/v1/api/seamless/{action}', [App\Http\Controllers\SeamlessApiController::class, 'handle']);

