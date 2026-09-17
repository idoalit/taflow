<?php

use App\Http\Controllers\Admin\ExamReleaseController;
use App\Http\Controllers\Admin\ExamRequestController;
use App\Http\Controllers\Admin\ExamScheduleController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LecturerController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RubricController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\ThesisSettingController;
use Illuminate\Support\Facades\Route;

/*
 * Admin Prodi. Every screen below reads the program studi from the active
 * workspace, so the same routes serve an administrator of two prodi without
 * the department ever appearing in a URL.
 */
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::controller(StudentController::class)->group(function (): void {
        Route::get('students', 'index')->name('students.index');
        Route::get('students/{student}', 'show')->name('students.show');
        Route::put('students/{student}/supervisors', 'updateSupervisors')->name('students.supervisors.update');
    });

    Route::controller(LecturerController::class)->group(function (): void {
        Route::get('lecturers', 'index')->name('lecturers.index');
        Route::get('lecturers/{lecturer}', 'show')->name('lecturers.show');
        Route::put('lecturers/{lecturer}', 'update')->name('lecturers.update');
    });

    Route::controller(ImportController::class)->group(function (): void {
        Route::get('import', 'index')->name('import.index');
        Route::get('import/{target}/template', 'template')->name('import.template');
        Route::post('import', 'store')->name('import.store');
    });

    Route::controller(ThesisSettingController::class)->group(function (): void {
        Route::get('thesis-settings', 'edit')->name('thesis-settings.edit');
        Route::put('thesis-settings', 'update')->name('thesis-settings.update');
    });

    Route::controller(MonitoringController::class)->group(function (): void {
        Route::get('monitoring', 'index')->name('monitoring.index');
        Route::get('monitoring/{student}', 'show')->name('monitoring.show');
    });

    Route::controller(ExamRequestController::class)->group(function (): void {
        Route::get('exam-requests', 'index')->name('exam-requests.index');
        Route::get('exam-requests/{stage}/{examRequest}', 'show')->name('exam-requests.show');
        Route::post('exam-requests/{stage}/{examRequest}', 'verify')->name('exam-requests.verify');
    });

    Route::controller(ExamScheduleController::class)->group(function (): void {
        Route::get('exams', 'index')->name('exams.index');
        Route::get('exams/{stage}/{examRequest}/schedule', 'create')->name('exams.create');
        Route::post('exams/{stage}/{examRequest}/schedule', 'store')->name('exams.store');
    });

    Route::controller(RubricController::class)->group(function (): void {
        Route::get('rubrics', 'index')->name('rubrics.index');
        Route::get('rubrics/create', 'create')->name('rubrics.create');
        Route::post('rubrics', 'store')->name('rubrics.store');
        Route::post('rubrics/assign', 'assign')->name('rubrics.assign');
        Route::get('rubrics/{rubric}', 'show')->name('rubrics.show');
        Route::get('rubrics/{rubric}/edit', 'edit')->name('rubrics.edit');
        Route::put('rubrics/{rubric}', 'update')->name('rubrics.update');
        Route::delete('rubrics/{rubric}', 'destroy')->name('rubrics.destroy');
        Route::post('rubrics/{rubric}/publish', 'publish')->name('rubrics.publish');
    });

    Route::controller(ExamReleaseController::class)->group(function (): void {
        Route::post('exams/{stage}/{exam}/release', 'store')->name('exams.release');
        Route::delete('exams/{stage}/{exam}/release', 'destroy')->name('exams.unlock');
    });

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});
