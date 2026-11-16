<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\InstitutionLogoController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// The front door is the sign-in screen itself: there is nothing here to read
// before signing in, so nobody is made to click through to it. Someone already
// signed in is sent on to their dashboard.
Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('home');

// Public by design: the browser's tab icon, the same logo the sign-in screen shows.
Route::get('logo-institusi', InstitutionLogoController::class)->name('institution.logo');

// Public by design: the page a document's QR code leads to.
Route::get('verifikasi/{token}', [DocumentVerificationController::class, 'show'])
    ->name('documents.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('workspace', [WorkspaceController::class, 'update'])->name('workspace.update');

    // Outside the superadmin routes on purpose: it is called from the session
    // of the account being looked at, which is never a superadmin's.
    Route::delete('impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/superadmin.php';
require __DIR__.'/thesis.php';
