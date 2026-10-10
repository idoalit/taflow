<?php

namespace App\Actions\Academic;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes a mahasiswa of a program studi: the account and the profile together,
 * or neither. The one place it happens, whether the row came from a
 * spreadsheet or from the form.
 */
final readonly class EnrollStudent
{
    /**
     * @param  array{name: string, email: string, student_number: string, enrollment_year: int|string}  $data
     */
    public function handle(Department $department, array $data): Student
    {
        return DB::transaction(function () use ($department, $data): Student {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                // No usable password until the person sets one through a reset
                // link; nobody is handed one in a spreadsheet or by an admin.
                'password' => Str::password(40),
            ]);
            $user->assignRole(UserRole::Student->value);

            return Student::create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'student_number' => $data['student_number'],
                'enrollment_year' => $data['enrollment_year'],
                'status' => StudentStatus::Active,
            ]);
        });
    }
}
