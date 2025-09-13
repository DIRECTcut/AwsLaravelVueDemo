<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Document routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('documents', DocumentController::class)->except(['create', 'edit']);
    Route::post('documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('api/documents/stats', [DocumentController::class, 'stats'])->name('documents.stats');
    Route::get('api/documents/recent', [DocumentController::class, 'recent'])->name('documents.recent');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
