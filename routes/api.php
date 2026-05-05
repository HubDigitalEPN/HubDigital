<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegistroDepositanteController;
use App\Http\Controllers\Auth\RegistroPrestamistraController;
use Illuminate\Support\Facades\Route;

Route::post('/register/prestamista', [RegistroPrestamistraController::class, 'store']);
Route::post('/register/depositante', [RegistroDepositanteController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
