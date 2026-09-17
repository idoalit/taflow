<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ResearchLogbookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property CarbonInterface $log_date
 * @property string $description
 * @property int $progress
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 */
#[Fillable(['student_id', 'log_date', 'description', 'progress', 'attachment_path', 'attachment_name'])]
class ResearchLogbook extends Model
{
    /** @use HasFactory<ResearchLogbookFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['log_date' => 'date'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
