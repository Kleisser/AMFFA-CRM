<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AltasBajasController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExternalCheckController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentasController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/forecast', [ForecastController::class, 'index']);
    Route::get('/external-checks', [ExternalCheckController::class, 'index']);
    Route::post('/contacts/{contact}/external-check', [ExternalCheckController::class, 'refresh']);
    Route::get('/external-checks/altas-bajas', [AltasBajasController::class, 'index']);
    Route::get('/ventas', [VentasController::class, 'index']);

    Route::apiResource('users', UserController::class);
    Route::patch('/users/{user}/supervisor', [UserController::class, 'reassignSupervisor']);
    Route::patch('/users/{user}/active', [UserController::class, 'toggleActive']);
    Route::get('/users/{user}/kpi', [UserController::class, 'kpi']);
    Route::apiResource('contacts', ContactController::class);
    Route::patch('/contacts/{contact}/stage', [ContactController::class, 'updateStage']);
    Route::get('/contacts/{contact}/timeline', [TimelineController::class, 'index']);
    Route::apiResource('products', ProductController::class);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans/{plan}', [PlanController::class, 'show']);
    Route::put('/plans/{plan}', [PlanController::class, 'update']);
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy']);
    Route::get('/plans/{plan}/prices', [PlanController::class, 'prices']);
    Route::post('/plans/{plan}/prices', [PlanController::class, 'storePrice']);
    Route::post('/plans/quote', [PlanController::class, 'quote']);
    Route::post('/plans/increase', [PlanController::class, 'increase']);
    Route::get('/plan-increases', [PlanController::class, 'increases']);
    Route::apiResource('pipelines', PipelineController::class);
    Route::apiResource('conversations', ConversationController::class)->only(['index', 'store', 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close']);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('reminders', ReminderController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('visits', VisitController::class);
    Route::apiResource('goals', GoalController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/zones', [ZoneController::class, 'index']);
    Route::post('/zones', [ZoneController::class, 'store']);
    Route::put('/zones/{zone}', [ZoneController::class, 'update']);
    Route::delete('/zones/{zone}', [ZoneController::class, 'destroy']);
    Route::get('/localities', [ZoneController::class, 'localities']);
    Route::post('/zones/{zone}/localities', [ZoneController::class, 'storeLocality']);
    Route::put('/localities/{locality}', [ZoneController::class, 'updateLocality']);
    Route::delete('/localities/{locality}', [ZoneController::class, 'destroyLocality']);
});
