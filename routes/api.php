<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentGeneratorController;
use App\Http\Controllers\Api\V1\DocumentAnalyzerController;
use App\Http\Controllers\Api\V1\DocumentContentController;
use App\Http\Controllers\Api\V1\LawyerController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\GeminiController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    Route::middleware(['web'])->group(function () {
        Route::get('/google', [AuthController::class, 'redirectToGoogle']);
        Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    });
    
    Route::post('/google', [AuthController::class, 'loginWithGoogle']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refreshToken']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::put('/users/profile', [UserController::class, 'updateProfile']);
    Route::get('/users/stats', [UserController::class, 'stats']);

    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/documents/generate', [DocumentController::class, 'generate']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
    Route::get('/documents/{id}/content', [DocumentController::class, 'getContent']);
    Route::post('/documents/analyze', [DocumentController::class, 'analyze']);
    Route::get('/lawyers/search', [LawyerController::class, 'search']);
    
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/chat/sessions/new', [ChatController::class, 'createSession']);
    Route::get('/chat/sessions', [ChatController::class, 'getAllSessions']);
    Route::get('/chat/sessions/latest', [ChatController::class, 'getSession']);
    Route::get('/chat/sessions/{sessionId}', [ChatController::class, 'getSession']);
});

