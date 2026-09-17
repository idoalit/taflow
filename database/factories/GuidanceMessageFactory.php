<?php

namespace Database\Factories;

use App\Models\GuidanceMessage;
use App\Models\GuidanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuidanceMessage>
 */
class GuidanceMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guidance_session_id' => GuidanceSession::factory(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(2),
        ];
    }
}
