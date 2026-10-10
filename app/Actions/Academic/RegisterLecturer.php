<?php

namespace App\Actions\Academic;

use App\Enums\LecturerStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes a dosen the system has not seen before. The program studi that
 * registers them becomes their homebase; any other prodi they work in is
 * added afterwards, by AssignLecturer.
 */
final readonly class RegisterLecturer
{
    public const DEFAULT_QUOTA = 10;

    /**
     * @param  array{name: string, email: string, lecturer_number: string, academic_position?: string|null, max_supervisions?: int|string|null}  $data
     */
    public function handle(Department $homebase, array $data): Lecturer
    {
        return DB::transaction(function () use ($homebase, $data): Lecturer {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Str::password(40),
            ]);
            $user->assignRole(UserRole::Lecturer->value);

            $lecturer = Lecturer::create([
                'user_id' => $user->id,
                'lecturer_number' => $data['lecturer_number'],
                'academic_position' => ($data['academic_position'] ?? null) ?: null,
                'max_supervisions' => $data['max_supervisions'] ?? self::DEFAULT_QUOTA,
                'status' => LecturerStatus::Active,
            ]);

            $lecturer->departments()->attach($homebase->id, ['is_primary' => true]);

            return $lecturer;
        });
    }
}
