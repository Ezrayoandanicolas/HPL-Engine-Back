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

// X-API Operator Seamless (batch_requests format)
Route::post('/seamless/balance', [App\Http\Controllers\SeamlessApiController::class, 'handle'])->defaults('action', 'getbalance');
Route::post('/seamless/withdraw', [App\Http\Controllers\SeamlessApiController::class, 'handle'])->defaults('action', 'withdraw');
Route::post('/seamless/deposit', [App\Http\Controllers\SeamlessApiController::class, 'handle'])->defaults('action', 'deposit');
Route::post('/seamless/pushbetdata', [App\Http\Controllers\SeamlessApiController::class, 'handle'])->defaults('action', 'pushbetdata');

// X-API Agent Seamless (apiType=0, format mirip DC /gold_api)
Route::post('/seamless', [App\Http\Controllers\SeamlessApiController::class, 'handleAgentSeamless']);

