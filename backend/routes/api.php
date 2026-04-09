<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\LoanRequestController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\Admin\FineController as AdminFineController;
use App\Http\Controllers\Api\Admin\HistoryController as AdminHistoryController;
use App\Http\Controllers\Api\Admin\LoanRequestController as AdminLoanRequestController;
use App\Http\Controllers\Api\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::apiResource('books', BookController::class)->only(['index', 'show']);

Route::middleware('auth:api')->group(function () {
    Route::apiResource('loan-requests', LoanRequestController::class)->only(['index', 'store', 'show']);
    Route::post('loan-requests/{id}/request-return', [LoanRequestController::class, 'requestReturn']);

    Route::get('fines', [FineController::class, 'index']);
    Route::get('history', [HistoryController::class, 'index']);
    Route::get('settings', [SettingsController::class, 'index']);
});

Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('books', BookController::class)->except(['index', 'show']);
    Route::apiResource('users', AdminUserController::class);
    Route::apiResource('loan-requests', AdminLoanRequestController::class);

    Route::get('stats', [AdminStatsController::class, 'index']);

    Route::get('settings', [AdminSettingsController::class, 'index']);
    Route::put('settings', [AdminSettingsController::class, 'update']);

    Route::get('fines', [AdminFineController::class, 'index']);
    Route::put('fines/{id}', [AdminFineController::class, 'update']);

    Route::get('history', [AdminHistoryController::class, 'index']);

    Route::get('reports/overview', [AdminReportsController::class, 'overview']);
});
