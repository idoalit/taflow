<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExamAttendanceController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamDocumentController;
use App\Http\Controllers\ExamMinutesController;
use App\Http\Controllers\ExamSignatureController;
use App\Http\Controllers\ExamVerificationController;
use App\Http\Controllers\GuidanceCardController;
use App\Http\Controllers\GuidanceMessageController;
use App\Http\Controllers\GuidanceSessionActionController;
use App\Http\Controllers\GuidanceSessionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerceptionAlignmentController;
use App\Http\Controllers\ProposalExamRequestController;
use App\Http\Controllers\ProposalReviewController;
use App\Http\Controllers\ResearchExamRequestController;
use App\Http\Controllers\ResearchLogbookController;
use App\Http\Controllers\RevisionNoteController;
use App\Http\Controllers\SupervisionScoreController;
use App\Http\Controllers\ThesisProposalController;
use Illuminate\Support\Facades\Route;

/*
 * Thesis supervision. Routes are grouped by resource rather than by role — the
 * controllers scope their queries with Student::visibleTo() and the policies
 * decide what each role may do, so a lecturer and a student reach the same
 * proposal through the same URL.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('documents', [DocumentController::class, 'show'])->name('documents.show');

    Route::controller(NotificationController::class)->group(function (): void {
        Route::get('notifications', 'index')->name('notifications.index');
        Route::post('notifications/read-all', 'readAll')->name('notifications.read-all');
        Route::post('notifications/{notification}/read', 'read')->name('notifications.read');
    });

    Route::controller(ThesisProposalController::class)->group(function (): void {
        Route::get('proposals', 'index')->name('proposals.index');
        Route::get('proposals/create', 'create')->name('proposals.create');
        Route::post('proposals', 'store')->name('proposals.store');
        Route::get('proposals/{proposal}', 'show')->name('proposals.show');
    });

    // Supervisor review of a proposal, and the Persamaan Persepsi round that
    // approval opens.
    Route::controller(ProposalReviewController::class)->group(function (): void {
        Route::post('proposals/{proposal}/approve', 'approve')->name('proposals.approve');
        Route::post('proposals/{proposal}/reject', 'reject')->name('proposals.reject');
    });

    Route::controller(PerceptionAlignmentController::class)->group(function (): void {
        Route::get('perception-alignments', 'index')->name('perception-alignments.index');
        Route::get('perception-alignments/{proposal}', 'show')->name('perception-alignments.show');
        Route::post('perception-alignments/{perceptionAlignment}/feedback', 'feedback')
            ->name('perception-alignments.feedback');
        Route::post('perception-alignments/{perceptionAlignment}/reject', 'reject')
            ->name('perception-alignments.reject');
    });

    Route::controller(ExamVerificationController::class)->group(function (): void {
        Route::get('exam-verifications', 'index')->name('exam-verifications.index');
        Route::get('exam-verifications/{stage}/{examRequest}', 'show')->name('exam-verifications.show');
        Route::post('exam-verifications/{stage}/{examRequest}', 'verify')->name('exam-verifications.verify');
    });

    Route::controller(ExamController::class)->group(function (): void {
        Route::get('exams', 'index')->name('exams.index');
        Route::get('exams/{stage}/{exam}', 'show')->name('exams.show');
        Route::get('exams/{stage}/{exam}/evaluate', 'evaluate')->name('exams.evaluate');
        Route::post('exams/{stage}/{exam}/evaluate', 'storeEvaluation')
            ->name('exams.evaluations.store');
    });

    Route::post('exams/{stage}/{exam}/attendance', [ExamAttendanceController::class, 'update'])
        ->name('exams.attendance.update');

    Route::get('exams/{stage}/{exam}/dokumen/{document}', [ExamDocumentController::class, 'show'])
        ->name('exams.documents.show');
    Route::get('exams/{stage}/{exam}/dokumen', [ExamDocumentController::class, 'all'])
        ->name('exams.documents.all');
    Route::post('exams/{stage}/{exam}/sign', [ExamSignatureController::class, 'store'])
        ->name('exams.sign');
    Route::post('exams/{stage}/{exam}/revisions', [RevisionNoteController::class, 'store'])
        ->name('exams.revisions.store');
    Route::post('revisions/{revisionNote}/toggle', [RevisionNoteController::class, 'toggle'])
        ->name('revisions.toggle');
    Route::post('exams/{stage}/{exam}/minutes', [ExamMinutesController::class, 'store'])
        ->name('exams.minutes.store');

    Route::controller(SupervisionScoreController::class)->group(function (): void {
        Route::get('exams/{stage}/{exam}/supervision', 'create')->name('exams.supervision.create');
        Route::post('exams/{stage}/{exam}/supervision', 'store')->name('exams.supervision.store');
    });

    Route::controller(GuidanceSessionController::class)->group(function (): void {
        Route::get('guidance-sessions', 'index')->name('guidance-sessions.index');
        Route::get('guidance-sessions/create', 'create')->name('guidance-sessions.create');
        Route::post('guidance-sessions', 'store')->name('guidance-sessions.store');
        Route::get('guidance-sessions/{guidanceSession}', 'show')->name('guidance-sessions.show');
        Route::post('guidance-sessions/{guidanceSession}/cancel', 'cancel')->name('guidance-sessions.cancel');
    });

    Route::post('guidance-sessions/{guidanceSession}/complete', [GuidanceSessionActionController::class, 'complete'])
        ->name('guidance-sessions.complete');

    Route::post('guidance-sessions/{guidanceSession}/messages', [GuidanceMessageController::class, 'store'])
        ->name('guidance-sessions.messages.store');

    Route::controller(ResearchLogbookController::class)->group(function (): void {
        Route::get('research-logbooks', 'index')->name('research-logbooks.index');
        Route::get('research-logbooks/create', 'create')->name('research-logbooks.create');
        Route::post('research-logbooks', 'store')->name('research-logbooks.store');
    });

    Route::controller(ProposalExamRequestController::class)->group(function (): void {
        Route::get('proposal-exam-requests', 'index')->name('proposal-exam-requests.index');
        Route::get('proposal-exam-requests/create', 'create')->name('proposal-exam-requests.create');
        Route::post('proposal-exam-requests', 'store')->name('proposal-exam-requests.store');
    });

    Route::controller(ResearchExamRequestController::class)->group(function (): void {
        Route::get('research-exam-requests', 'index')->name('research-exam-requests.index');
        Route::get('research-exam-requests/create', 'create')->name('research-exam-requests.create');
        Route::post('research-exam-requests', 'store')->name('research-exam-requests.store');
    });

    Route::controller(GuidanceCardController::class)->group(function (): void {
        Route::get('guidance-cards', 'index')->name('guidance-cards.index');
        Route::get('guidance-cards/{stage}/print', 'print')->name('guidance-cards.print');
        Route::get('guidance-cards/{stage}/print/{student}', 'print')->name('guidance-cards.print-for-student');
    });
});
