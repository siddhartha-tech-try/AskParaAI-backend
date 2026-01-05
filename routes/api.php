<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\QuestionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', [HealthController::class, 'index']);
Route::post('/generate', [ContextController::class, 'generate']);
Route::post('/generate-questions', [QuestionController::class, 'generate']);
