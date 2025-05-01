<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\V1\GeminiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    
    Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::post('auth/google/token', [AuthController::class, 'loginWithGoogle']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('change-password', [AuthController::class, 'changePassword']);

        Route::post('gemini/generate', [GeminiController::class, 'generateContent']);
    });
});

