<?php

namespace Database\Factories;

use App\Enums\SupervisorRole;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudentSupervisor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSupervisor>
 */
class StudentSupervisorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'lecturer_id' => Lecturer::factory(),
            'role' => SupervisorRole::Supervisor,
            'order' => 1,
            'assigned_at' => now(),
        ];
    }

    public function examiner(int $order = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => SupervisorRole::Examiner,
            'order' => $order,
        ]);
    }
}
