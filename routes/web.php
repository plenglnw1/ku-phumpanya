<?php

declare(strict_types=1);

use App\Http\Controllers\LearningPathController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SmartPicksController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('guest')->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
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

require __DIR__.'/auth.php';
