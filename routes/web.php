<?php

declare(strict_types=1);

use App\Http\Controllers\KnowledgeGraphController;
use App\Http\Controllers\LearningPathController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SmartPicksController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('guest')->name('welcome');

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('search.index'))->name('dashboard');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::post('/search', [SearchController::class, 'store'])->name('search.store');
    Route::get('/search/history/{searchHistory}', [SearchController::class, 'show'])->name('search.show');

    Route::get('/learning', [LearningPathController::class, 'show'])->name('learning.show');
    Route::get('/learning/{searchHistory}', [LearningPathController::class, 'show'])->name('learning.show.topic');

    Route::get('/smart-picks', [SmartPicksController::class, 'index'])->name('smart-picks.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'can:view-knowledge-graph'])->prefix('graph')->name('graph.')->group(function () {
    Route::get('/', [KnowledgeGraphController::class, 'index'])->name('index');
    Route::get('/overview', [KnowledgeGraphController::class, 'overview'])->name('overview');
    Route::get('/hub', [KnowledgeGraphController::class, 'hub'])->name('hub');
    Route::get('/search', [KnowledgeGraphController::class, 'search'])->name('search');
    Route::get('/neighbours', [KnowledgeGraphController::class, 'neighbours'])->name('neighbours');
    Route::post('/cypher', [KnowledgeGraphController::class, 'cypher'])->middleware('throttle:30,1')->name('cypher');
});

require __DIR__.'/auth.php';
