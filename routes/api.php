<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\V1\GeminiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

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
