<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SmartPicksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/reference/faculties', [ReferenceController::class, 'faculties']);
Route::get('/reference/roles', [ReferenceController::class, 'roles']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/register/complete', [AuthController::class, 'completeRegistration']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['verified', 'profile.complete'])->group(function () {
        Route::post('/search', [SearchController::class, 'store']);
        Route::get('/search/recent', [SearchController::class, 'recent']);
        Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
        Route::get('/search/history/{searchHistory}', [SearchController::class, 'show']);

        Route::get('/learning', [LearningController::class, 'show']);
        Route::get('/smart-picks', [SmartPicksController::class, 'index']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
    });
});
