<?php

use App\Http\Controllers\SuperAdmin\ActivityLogController;
use App\Http\Controllers\SuperAdmin\BroadcastController;
use App\Http\Controllers\SuperAdmin\DepartmentAdminController;
use App\Http\Controllers\SuperAdmin\DepartmentController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SystemSettingController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\UserInvitationController;
use App\Http\Controllers\SuperAdmin\UserRoleController;
use App\Http\Middleware\EnsureSuperAdminWorkspace;
use Illuminate\Support\Facades\Route;

/*
 * Superadmin. Everything here spans every program studi, so it answers only to
 * the Superadmin workspace — a superadmin who has stepped into a prodi as its
 * Admin Prodi uses the admin routes like any other admin, and is refused here.
 */
Route::middleware(['auth', 'verified', EnsureSuperAdminWorkspace::class])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function (): void {
        Route::controller(DepartmentController::class)->group(function (): void {
            Route::get('departments', 'index')->name('departments.index');
            Route::get('departments/create', 'create')->name('departments.create');
            Route::post('departments', 'store')->name('departments.store');
            Route::get('departments/{department}', 'show')->name('departments.show');
            Route::get('departments/{department}/edit', 'edit')->name('departments.edit');
            Route::put('departments/{department}', 'update')->name('departments.update');
            Route::delete('departments/{department}', 'destroy')->name('departments.destroy');
        });

        Route::controller(DepartmentAdminController::class)->group(function (): void {
            Route::post('departments/{department}/admins', 'store')->name('departments.admins.store');
            Route::delete('departments/{department}/admins/{user}', 'destroy')->name('departments.admins.destroy');
        });

        Route::controller(UserController::class)->group(function (): void {
            Route::get('users', 'index')->name('users.index');
            Route::get('users/create', 'create')->name('users.create');
            Route::post('users', 'store')->name('users.store');
            Route::get('users/{user}', 'show')->name('users.show');
            Route::put('users/{user}', 'update')->name('users.update');
            Route::delete('users/{user}', 'destroy')->name('users.destroy');
        });

        Route::put('users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');

        // The activation link is mail, so it is throttled like any other.
        Route::post('users/{user}/invitation', [UserInvitationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('users.invitation.store');

        Route::controller(RoleController::class)->group(function (): void {
            Route::get('roles', 'index')->name('roles.index');
            Route::put('roles/{role}', 'update')->name('roles.update');
        });

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::controller(BroadcastController::class)->group(function (): void {
            Route::get('broadcasts', 'index')->name('broadcasts.index');
            Route::get('broadcasts/create', 'create')->name('broadcasts.create');
            Route::post('broadcasts', 'store')->name('broadcasts.store');
        });

        Route::controller(SystemSettingController::class)->group(function (): void {
            Route::get('settings', 'edit')->name('settings.edit');
            // POST rather than PUT: the form carries the logo file.
            Route::post('settings/institution', 'updateInstitution')->name('settings.institution.update');
            Route::delete('settings/institution/logo', 'destroyLogo')->name('settings.institution.logo.destroy');
            Route::put('settings/thesis-defaults', 'updateThesisDefaults')->name('settings.thesis-defaults.update');
        });
    });
