<?php

namespace App\Enums;

use App\Models\ProposalExam;
use App\Models\ProposalExamRequest;
use App\Models\ResearchExam;
use App\Models\ResearchExamRequest;
use App\Models\ThesisExam;
use App\Models\ThesisExamRequest;

/**
 * The two exams a thesis passes through. The values match the stage keys the
 * design's Pelaksanaan Ujian tabs use, so they travel unchanged into URLs.
 */
enum ExamStage: string
{
    case Proposal = 'proposal';
    case Research = 'hasil';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Ujian Proposal',
            self::Research => 'Ujian Hasil',
        };
    }

    /**
     * The longer form, used where the label stands alone rather than in a tab.
     */
    public function title(): string
    {
        return match ($this) {
            self::Proposal => 'Ujian Proposal',
            self::Research => 'Ujian Hasil Penelitian',
        };
    }

    /**
     * @return class-string<ThesisExam>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Proposal => ProposalExam::class,
            self::Research => ResearchExam::class,
        };
    }

    /**
     * @return class-string<ThesisExamRequest>
     */
    public function requestModelClass(): string
    {
        return match ($this) {
            self::Proposal => ProposalExamRequest::class,
            self::Research => ResearchExamRequest::class,
        };
    }

    /**
     * Where a student manages their own requests for this stage.
     */
    public function requestRouteName(): string
    {
        return match ($this) {
            self::Proposal => 'proposal-exam-requests.index',
            self::Research => 'research-exam-requests.index',
        };
    }

    /**
     * Which guidance stage has to be complete before this exam can be sat.
     */
    public function guidanceStage(): GuidanceStage
    {
        return match ($this) {
            self::Proposal => GuidanceStage::Proposal,
            self::Research => GuidanceStage::Research,
        };
    }

    /**
     * The alias this stage's exams are stored under in polymorphic columns.
     */
    public function morphAlias(): string
    {
        return match ($this) {
            self::Proposal => 'proposal_exam',
            self::Research => 'research_exam',
        };
    }
}
