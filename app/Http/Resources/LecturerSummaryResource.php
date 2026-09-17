<?php

namespace App\Http\Resources;

use App\Enums\SupervisorRole;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Position label comes from the pivot when the lecturer was loaded through a
 * student's supervisor or examiner relation.
 *
 * @mixin Lecturer
 */
class LecturerSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'initials' => $this->user->initials(),
            'position_label' => $this->positionLabel(),
            'academic_position' => $this->academic_position,
        ];
    }

    private function positionLabel(): string
    {
        $pivot = $this->resource->pivot;

        if ($pivot === null) {
            return 'Dosen';
        }

        $role = $pivot->role instanceof SupervisorRole
            ? $pivot->role
            : SupervisorRole::from((string) $pivot->role);

        return $role->label().' '.$pivot->order;
    }
}
