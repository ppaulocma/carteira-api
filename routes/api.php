<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [WalletController::class, 'user']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
    Route::get('/users/find', [WalletController::class, 'findUser']);

    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/transfer', [WalletController::class, 'transfer']);
    Route::post('/transactions/{id}/reverse', [WalletController::class, 'reverse']);
});
