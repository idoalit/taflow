<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public by design: the page a document's QR code leads to.
Route::get('verifikasi/{token}', [DocumentVerificationController::class, 'show'])
    ->name('documents.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('workspace', [WorkspaceController::class, 'update'])->name('workspace.update');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/thesis.php';
