<?php

namespace App\Actions\Thesis;

use App\Enums\ExamParticipantRole;
use App\Models\ExamEvaluation;
use App\Models\GradeScale;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionScore;
use App\Models\ThesisExam;
use Illuminate\Support\Collection;

/**
 * The final grade, composed from the two marks an exam produces.
 *
 * nilai_ujian     mean of the examiners' totals — divided by however many
 *                 actually marked, so three examiners average correctly
 * nilai_bimbingan sum of the supervision indicators, averaged over the
 *                 pembimbing who marked
 * nilai_akhir     the two, weighted by the program studi's own numbers
 *
 * Nothing here is entered by hand, and the weights are read from settings
 * rather than written into the arithmetic.
 */
final readonly class ComposeFinalGrade
{
    /**
     * @return array{
     *     exam_score: float|null,
     *     exam_scores: array<int, array{lecturer: string, score: float}>,
     *     examiner_count: int,
     *     supervision_score: float|null,
     *     supervision_max: int,
     *     exam_weight: float,
     *     supervision_weight: float,
     *     final_score: float|null,
     *     letter: string|null,
     *     complete: bool,
     * }
     */
    public function handle(ThesisExam $exam): array
    {
        $setting = $exam->student->department->activeThesisSetting();
        $examWeight = (float) $setting->exam_score_weight;
        $supervisionWeight = (float) $setting->supervision_score_weight;

        $evaluations = $exam->evaluations()->with('lecturer.user')->get();
        $examScores = $evaluations
            ->map(fn (ExamEvaluation $evaluation): array => [
                'lecturer' => $evaluation->lecturer->user->name,
                'score' => round((float) $evaluation->final_score, 2),
            ])
            ->values()
            ->all();

        $examScore = $evaluations->isEmpty()
            ? null
            : round($evaluations->sum(fn (ExamEvaluation $e): float => (float) $e->final_score)
                / $evaluations->count(), 2);

        $supervision = SupervisionScore::query()
            ->where('exam_type', $exam->getMorphClass())
            ->where('exam_id', $exam->id)
            ->get();

        // Each pembimbing marks the same five indicators out of 100 between
        // them, so two supervisors average — summing would put the component
        // out of its own scale.
        $perSupervisor = $supervision->groupBy('lecturer_id');

        $supervisionScore = $perSupervisor->isEmpty()
            ? null
            : round($perSupervisor
                ->map(fn (Collection $rows): float => $rows->sum(
                    fn (SupervisionScore $score): float => (float) $score->score,
                ))
                ->avg(), 2);

        $final = $examScore === null || $supervisionScore === null
            ? null
            : round($examScore * $examWeight + $supervisionScore * $supervisionWeight, 2);

        return [
            'exam_score' => $examScore,
            'exam_scores' => $examScores,
            'examiner_count' => $evaluations->count(),
            'supervision_score' => $supervisionScore,
            'supervision_max' => $perSupervisor->isEmpty()
                ? 0
                : (int) $perSupervisor->first()->sum('max_score'),
            'exam_weight' => $examWeight,
            'supervision_weight' => $supervisionWeight,
            'final_score' => $final,
            'letter' => GradeScale::letterFor($exam->student->department, $final),
            'complete' => $final !== null,
        ];
    }

    /**
     * What still stands between this sitting and a settled result, named so the
     * screen can say so rather than print an empty document.
     *
     * Both the berita acara and the release refuse while this is non-empty.
     *
     * @return array<int, string>
     */
    public function missing(ThesisExam $exam): array
    {
        $missing = [];

        // A verdict on someone who is not recorded as having been there is not
        // a sitting that happened. Only checked once a panel has been seated:
        // an exam falling back to the supervisor roster has no seat for the
        // mahasiswa to be marked in.
        $examinee = $exam->participants()
            ->where('role', ExamParticipantRole::Student)
            ->first();

        if ($examinee !== null && ! $examinee->attended) {
            $missing[] = 'Mahasiswa belum ditandai hadir pada presensi.';
        }

        $panelSize = $exam->panel()->count();
        $scored = $exam->evaluations()->count();

        if ($scored < $panelSize) {
            $missing[] = 'Penilaian ujian belum lengkap ('.$scored.' dari '.$panelSize.' penguji).';
        }

        $supervisors = $exam->participants()
            ->where('role', ExamParticipantRole::Supervisor)
            ->pluck('lecturer_id')
            ->filter();

        $indicatorCount = SupervisionIndicator::activeFor(
            $exam->student->department,
        )->count();

        foreach ($supervisors as $lecturerId) {
            $marked = SupervisionScore::query()
                ->where('exam_type', $exam->getMorphClass())
                ->where('exam_id', $exam->id)
                ->where('lecturer_id', $lecturerId)
                ->count();

            if ($marked < $indicatorCount) {
                $missing[] = 'Penilaian proses bimbingan belum diisi lengkap oleh pembimbing.';

                break;
            }
        }

        if ($supervisors->isEmpty()) {
            $missing[] = 'Belum ada pembimbing pada panel sidang ini.';
        }

        return $missing;
    }
}
