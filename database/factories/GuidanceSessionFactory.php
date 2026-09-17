<?php

namespace Database\Factories;

use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Enums\MeetingType;
use App\Models\GuidanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuidanceSession>
 */
class GuidanceSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'created_by' => User::factory(),
            'title' => 'Bimbingan '.fake()->sentence(3),
            'stage' => GuidanceStage::Proposal,
            'target_type' => GuidanceTargetType::Single,
            'guidance_type' => GuidanceType::FaceToFace,
            'meeting_type' => MeetingType::Offline,
            'meeting_location' => 'Ruang Dosen '.fake()->numberBetween(101, 405),
            'meeting_scheduled_at' => now()->addDays(3),
            'status' => GuidanceStatus::Ongoing,
        ];
    }

    public function async(): static
    {
        return $this->state(fn (array $attributes): array => [
            'guidance_type' => GuidanceType::OnlineAsync,
            'meeting_type' => null,
            'meeting_location' => null,
            'meeting_scheduled_at' => null,
        ]);
    }

    public function research(): static
    {
        return $this->state(fn (array $attributes): array => ['stage' => GuidanceStage::Research]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GuidanceStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
