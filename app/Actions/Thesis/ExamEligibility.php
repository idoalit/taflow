<?php

namespace App\Actions\Thesis;

use App\Enums\GuidanceStage;
use App\Models\DepartmentThesisSetting;
use App\Models\Student;

/**
 * Answers whether a student may submit an exam request, and why not when they
 * may not. The redesign surfaces these checks to the student up front — on the
 * request screens — rather than failing the submission after the fact.
 */
final readonly class ExamEligibility
{
    /**
     * @param  array<int, array{key: string, label: string, met: bool, detail: string}>  $checks
     */
    private function __construct(
        public bool $eligible,
        public array $checks,
        public string $summary,
    ) {}

    public static function forProposalExam(Student $student): self
    {
        $settings = self::settingsFor($student);

        // A program studi may run no proposal exam at all, in which case there
        // is nothing to request.
        if (! $settings->requires_proposal_exam) {
            return new self(
                false,
                [],
                'Program studi Anda tidak mewajibkan ujian proposal, sehingga tidak ada pengajuan '
                .'ujian proposal yang perlu dikirim.',
            );
        }

        $proposal = $student->activeProposal();
        $completed = $student->completedGuidanceCount(GuidanceStage::Proposal);
        $required = $settings->min_proposal_guidance_sessions;

        $checks = [
            [
                'key' => 'proposal_approved',
                'label' => 'Proposal disetujui pembimbing',
                'met' => $proposal?->isApproved() ?? false,
                'detail' => $proposal?->isApproved()
                    ? 'Proposal Anda sudah disetujui.'
                    : 'Proposal harus disetujui sebelum ujian dapat diajukan.',
            ],
            [
                'key' => 'guidance_quota',
                'label' => 'Bimbingan proposal selesai',
                'met' => $completed >= $required,
                'detail' => "{$completed} dari {$required} bimbingan selesai.",
            ],
        ];

        return self::fromChecks($checks, "{$completed} dari {$required} bimbingan selesai");
    }

    public static function forResearchExam(Student $student): self
    {
        $settings = self::settingsFor($student);
        $progress = $student->latestLogbookProgress();
        $requiredProgress = $settings->min_logbook_progress;
        $completed = $student->completedGuidanceCount(GuidanceStage::Research);
        $required = $settings->min_research_guidance_sessions;

        $checks = [];

        // The research stage sits behind the proposal exam wherever the program
        // studi runs one, so the sidang cannot be requested before it is passed.
        if ($settings->requires_proposal_exam) {
            $passed = $student->hasPassedProposalExam();

            $checks[] = [
                'key' => 'proposal_exam',
                'label' => 'Lulus ujian proposal',
                'met' => $passed,
                'detail' => $passed ? 'Sudah lulus.' : 'Belum lulus ujian proposal.',
            ];
        }

        $checks[] = [
            'key' => 'logbook_progress',
            'label' => 'Progres logbook penelitian',
            'met' => $progress >= $requiredProgress,
            'detail' => "{$progress}% / {$requiredProgress}%",
        ];

        $checks[] = [
            'key' => 'guidance_quota',
            'label' => 'Bimbingan hasil penelitian selesai',
            'met' => $completed >= $required,
            'detail' => "{$completed} / {$required}",
        ];

        return self::fromChecks(
            $checks,
            "Progres logbook penelitian {$progress}% dari {$requiredProgress}% yang disyaratkan, dan {$completed} dari {$required} bimbingan hasil penelitian selesai"
        );
    }

    /**
     * @param  array<int, array{key: string, label: string, met: bool, detail: string}>  $checks
     */
    private static function fromChecks(array $checks, string $progressSummary): self
    {
        $eligible = ! in_array(false, array_column($checks, 'met'), true);

        return new self(
            $eligible,
            $checks,
            $eligible
                ? "{$progressSummary} — Anda telah memenuhi syarat untuk mengajukan ujian."
                : "{$progressSummary}. Lengkapi seluruh syarat untuk dapat mengajukan.",
        );
    }

    private static function settingsFor(Student $student): DepartmentThesisSetting
    {
        return $student->department->activeThesisSetting();
    }

    /**
     * @return array{eligible: bool, checks: array<int, array{key: string, label: string, met: bool, detail: string}>, summary: string}
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'checks' => $this->checks,
            'summary' => $this->summary,
        ];
    }
}
