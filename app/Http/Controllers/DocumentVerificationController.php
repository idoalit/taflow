<?php

namespace App\Http\Controllers;

use App\Models\ProposalExam;
use App\Models\ResearchExam;
use App\Models\ThesisExam;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a document's QR code leads to.
 *
 * Public by design: whoever holds the printed page can confirm it is genuine.
 * It says only what the document itself already says, and never the marks
 * behind it.
 */
class DocumentVerificationController extends Controller
{
    public function show(string $token): Response
    {
        $exam = $this->findByToken($token);

        abort_if($exam === null, 404);

        $exam->load(['student.user', 'student.department', 'minutes']);
        $minutes = $exam->minutes;

        return Inertia::render('verify', [
            'document' => [
                'valid' => true,
                'student' => $exam->student->user->name,
                'student_number' => $exam->student->student_number,
                'department' => $exam->student->department->name,
                'faculty' => $exam->student->department->faculty,
                'stage' => $exam->stage()->title(),
                'title' => $exam->title,
                'examined_at' => $exam->scheduled_at->translatedFormat('l, d F Y'),
                'decision' => $minutes?->decision->label(),
                'final_score' => $minutes?->final_score,
                'letter' => $minutes?->letter,
                'released_at' => $exam->released_at?->translatedFormat('d F Y'),
                'institution' => config('institution.name'),
            ],
        ]);
    }

    private function findByToken(string $token): ?ThesisExam
    {
        foreach ([ProposalExam::class, ResearchExam::class] as $model) {
            $exam = $model::query()
                ->where('verification_token', $token)
                ->whereNotNull('released_at')
                ->first();

            if ($exam !== null) {
                return $exam;
            }
        }

        return null;
    }
}
