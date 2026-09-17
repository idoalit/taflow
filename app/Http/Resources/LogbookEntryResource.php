<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Models\ResearchLogbook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The logbook timeline splits the date across the gutter, so day and month are
 * sent separately.
 *
 * @mixin ResearchLogbook
 */
class LogbookEntryResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->log_date;

        return [
            'id' => $this->id,
            'day' => $date->isoFormat('D'),
            'month_year' => $date->isoFormat('MMM YYYY'),
            'weekday' => $date->isoFormat('dddd'),
            'progress' => $this->progress,
            'description' => $this->description,
            'attachment' => DocumentResource::fromColumns(
                $this->attachment_path,
                $this->attachment_name,
                null,
                $this->created_at,
            ),
        ];
    }
}
