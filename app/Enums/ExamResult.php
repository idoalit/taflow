<?php

namespace App\Enums;

enum ExamResult: string
{
    case Passed = 'passed';
    case ConditionalPass = 'conditional_pass';
    case Failed = 'failed';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Lulus',
            self::ConditionalPass => 'Lulus Bersyarat',
            self::Failed => 'Tidak Lulus',
        };
    }

    /**
     * Whether this outcome lets the student move on to the next stage of the
     * thesis. A conditional pass carries revisions but does not hold the
     * student back; only an outright failure does.
     */
    public function unlocksNextStage(): bool
    {
        return match ($this) {
            self::Passed, self::ConditionalPass => true,
            self::Failed => false,
        };
    }
}
