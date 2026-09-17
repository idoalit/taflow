<?php

namespace App\Actions\Thesis;

use App\Enums\ExamStatus;
use App\Enums\GuidanceStage;
use App\Models\ProposalExam;
use App\Models\Student;

/**
 * Answers which phase of the thesis a student has reached, and why not when
 * they have not reached it yet.
 *
 * The academic flow is sequential: proposal guidance runs until the proposal
 * exam, and only a student who has passed that exam moves on to research
 * guidance. A program studi that does not run a proposal exam at all replaces
 * that gate with its own minimum number of completed proposal sessions.
 *
 * This is the single home for that rule. Everything gated on reaching the
 * research phase reads it rather than restating it: the guidance-session
 * policy, its form request and create screen, and the research logbook.
 */
final readonly class ThesisPhaseAccess
{
    /**
     * @param  array<string, string|null>  $reasons  Locked reason per stage value, null when open.
     */
    private function __construct(private array $reasons) {}

    public static function forStudent(Student $student): self
    {
        return new self([
            GuidanceStage::Proposal->value => self::proposalReason($student),
            GuidanceStage::Research->value => self::researchReason($student),
        ]);
    }

    /**
     * Whether the student has entered the research phase at all — the gate that
     * opens research-stage guidance and the research logbook alike.
     */
    public function researchPhaseOpen(): bool
    {
        return $this->isOpen(GuidanceStage::Research);
    }

    public function researchPhaseReason(): ?string
    {
        return $this->reasonFor(GuidanceStage::Research);
    }

    public function isOpen(GuidanceStage $stage): bool
    {
        return $this->reasons[$stage->value] === null;
    }

    /**
     * The explanation shown when a stage is locked; null when it is open.
     */
    public function reasonFor(GuidanceStage $stage): ?string
    {
        return $this->reasons[$stage->value];
    }

    /**
     * @return array<int, GuidanceStage>
     */
    public function openStages(): array
    {
        return array_values(array_filter(
            GuidanceStage::cases(),
            fn (GuidanceStage $stage): bool => $this->isOpen($stage),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function openStageValues(): array
    {
        return array_map(fn (GuidanceStage $stage): string => $stage->value, $this->openStages());
    }

    /**
     * Options for the segmented control on the create screen, carrying the
     * locked state and its reason so the form can explain itself.
     *
     * @return array<int, array{value: string, label: string, disabled: bool, reason: string|null}>
     */
    public function toOptions(): array
    {
        return array_map(fn (GuidanceStage $stage): array => [
            'value' => $stage->value,
            'label' => $stage->label(),
            'disabled' => ! $this->isOpen($stage),
            'reason' => $this->reasonFor($stage),
        ], GuidanceStage::cases());
    }

    /**
     * Proposal guidance opens as soon as the supervisors approve the proposal.
     */
    private static function proposalReason(Student $student): ?string
    {
        $proposal = $student->activeProposal();

        if ($proposal === null) {
            return 'Anda belum mengajukan proposal tugas akhir. Ajukan proposal terlebih dahulu, '
                .'lalu sesi bimbingan dapat dibuat setelah proposal disetujui pembimbing.';
        }

        if (! $proposal->isApproved()) {
            return 'Proposal Anda berstatus '.strtolower($proposal->status->label()).'. '
                .'Sesi bimbingan baru dapat dibuat setelah proposal disetujui pembimbing.';
        }

        return null;
    }

    /**
     * Research guidance opens after the proposal exam — or, where the program
     * studi runs no proposal exam, after its minimum proposal guidance quota.
     */
    private static function researchReason(Student $student): ?string
    {
        $blocked = self::proposalReason($student);

        if ($blocked !== null) {
            return $blocked;
        }

        $settings = $student->department->activeThesisSetting();

        if ($settings->requires_proposal_exam) {
            return $student->hasPassedProposalExam()
                ? null
                : self::proposalExamReason($student->latestProposalExam());
        }

        $completed = $student->completedGuidanceCount(GuidanceStage::Proposal);
        $required = $settings->min_proposal_guidance_sessions;

        if ($completed >= $required) {
            return null;
        }

        return 'Program studi Anda tidak mewajibkan ujian proposal, namun bimbingan hasil penelitian '
            ."baru terbuka setelah {$required} bimbingan proposal selesai. Saat ini {$completed} dari "
            ."{$required} bimbingan proposal telah selesai.";
    }

    /**
     * Says where the student actually stands with their proposal exam, so a
     * blocked student knows whether to wait for a schedule, wait for a result,
     * or resit — rather than reading one flat refusal for all three.
     */
    private static function proposalExamReason(?ProposalExam $exam): string
    {
        $opening = 'Tahap penelitian terbuka setelah Anda dinyatakan lulus ujian proposal. ';

        if ($exam === null) {
            return $opening.'Ujian proposal Anda belum dijadwalkan — selesaikan bimbingan proposal, '
                .'lalu ajukan ujian proposal terlebih dahulu.';
        }

        if (! in_array($exam->status, ExamStatus::decided(), true)) {
            // Carbon's locale is set application-wide in AppServiceProvider.
            return $opening.'Ujian proposal Anda dijadwalkan pada '
                .$exam->scheduled_at->isoFormat('dddd, D MMMM YYYY').'.';
        }

        if ($exam->result === null) {
            return $opening.'Hasil ujian proposal Anda belum diumumkan.';
        }

        return 'Hasil ujian proposal Anda: '.$exam->result->label().'. '
            .'Tahap penelitian baru terbuka setelah Anda lulus ujian proposal.';
    }
}
