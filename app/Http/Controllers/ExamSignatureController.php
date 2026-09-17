<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\CaptureSignature;
use App\Enums\ExamStage;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Signing your own seat at a sitting.
 *
 * A person signs for themselves and nobody else — there is no administrative
 * override here, unlike attendance, because a signature is a personal claim.
 */
class ExamSignatureController extends Controller
{
    public function store(
        Request $request,
        ExamStage $stage,
        int $exam,
        CaptureSignature $capture,
    ): RedirectResponse {
        $thesisExam = $stage->modelClass()::query()->where('id', $exam)->firstOrFail();

        Gate::authorize('view', $thesisExam);

        $participant = $thesisExam->participants()
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if($participant === null, 403, 'Anda bukan peserta sidang ini.');

        abort_if(
            $thesisExam->isReleased(),
            403,
            'Hasil ujian sudah dirilis dan terkunci.',
        );

        $request->validate([
            'signature' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [], [
            'signature' => 'tanda tangan',
            'file' => 'berkas tanda tangan',
        ]);

        $signature = match (true) {
            $request->hasFile('file') => $capture->fromUpload($request->user(), $request->file('file'), $request),
            filled($request->input('signature')) => $capture->fromDataUri(
                $request->user(),
                (string) $request->input('signature'),
                $request,
            ),
            default => throw ValidationException::withMessages([
                'signature' => 'Gambar tanda tangan Anda atau unggah berkasnya.',
            ]),
        };

        $participant->update([
            'signature_id' => $signature->id,
            'signed_at' => now(),
            // Signing is itself a statement of having been there.
            'attended' => true,
            'attended_at' => $participant->attended_at ?? now(),
        ]);

        AuditLog::record(
            $participant,
            'participant.signed',
            $request->user(),
            changes: ['role' => $participant->role->value],
            request: $request,
        );

        return back()->with('success', 'Tanda tangan tersimpan.');
    }
}
