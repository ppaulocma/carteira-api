<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/transfer', [WalletController::class, 'transfer']);
    Route::post('/transactions/{id}/reverse', [WalletController::class, 'reverse']);
});
