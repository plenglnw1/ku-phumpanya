<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\DashboardStatsController;
use App\Http\Controllers\Api\Admin\SearchLogController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\LearningProgressController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SmartPicksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/reference/faculties', [ReferenceController::class, 'faculties']);
Route::get('/reference/roles', [ReferenceController::class, 'roles']);

Route::post('/auth/login', [SessionController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/register/complete', [AuthController::class, 'completeRegistration']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/stats', [DashboardStatsController::class, 'index']);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/search-logs/export', [SearchLogController::class, 'export']);
        Route::get('/search-logs', [SearchLogController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
    });

    Route::middleware(['verified', 'profile.complete'])->group(function () {
        Route::post('/search', [SearchController::class, 'store']);
        Route::get('/search/recent', [SearchController::class, 'recent']);
        Route::delete('/search/recent', [SearchController::class, 'clear']);
        Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
        Route::get('/search/history/{searchHistory}', [SearchController::class, 'show']);
        Route::get('/search/history/{searchHistory}/progress', [LearningProgressController::class, 'show']);
        Route::put('/search/history/{searchHistory}/progress', [LearningProgressController::class, 'update']);

        Route::get('/learning', [LearningController::class, 'show']);
        Route::get('/smart-picks', [SmartPicksController::class, 'index']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
    });
});
