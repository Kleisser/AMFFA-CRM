<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/forecast', [ForecastController::class, 'index']);

    Route::apiResource('users', UserController::class);
    Route::get('/users/{user}/kpi', [UserController::class, 'kpi']);
    Route::apiResource('contacts', ContactController::class);
    Route::patch('/contacts/{contact}/stage', [ContactController::class, 'updateStage']);
    Route::get('/contacts/{contact}/timeline', [TimelineController::class, 'index']);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('pipelines', PipelineController::class);
    Route::apiResource('conversations', ConversationController::class)->only(['index', 'store', 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close']);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('reminders', ReminderController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('visits', VisitController::class);
    Route::apiResource('goals', GoalController::class)->only(['index', 'store', 'update', 'destroy']);
});
