<?php

namespace App\Imports;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk enrolment from the downloadable template. Rows are validated one by one
 * and reported back by line number, so a single bad row does not sink the file.
 */
class StudentImport implements ToCollection, WithHeadingRow
{
    public const COLUMNS = ['nama', 'email', 'nim', 'angkatan'];

    public int $imported = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private readonly Department $department) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // +2: the heading row, and spreadsheets counting from one.
            $line = $index + 2;
            $data = $this->normalise($row);

            if ($this->isBlank($data)) {
                continue;
            }

            $validator = Validator::make($data, [
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'nim' => ['required', 'string', 'max:50', 'unique:students,student_number'],
                'angkatan' => ['required', 'integer', 'min:2000', 'max:2100'],
            ], [
                'email.unique' => 'surel sudah terdaftar',
                'nim.unique' => 'NIM sudah terdaftar',
            ]);

            if ($validator->fails()) {
                $this->errors[] = "Baris {$line}: ".implode(', ', $validator->errors()->all());

                continue;
            }

            $this->create($validator->validated());
            $this->imported++;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                // Imported accounts have no usable password until the person
                // resets it; nobody is handed one in a spreadsheet.
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->assignRole(UserRole::Student->value);

            Student::create([
                'user_id' => $user->id,
                'department_id' => $this->department->id,
                'student_number' => $data['nim'],
                'enrollment_year' => $data['angkatan'],
                'status' => StudentStatus::Active,
            ]);
        });
    }

    /**
     * @param  Collection<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalise(Collection $row): array
    {
        return [
            'nama' => trim((string) $row->get('nama')),
            'email' => strtolower(trim((string) $row->get('email'))),
            'nim' => trim((string) $row->get('nim')),
            'angkatan' => $row->get('angkatan'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isBlank(array $data): bool
    {
        return $data['nama'] === '' && $data['email'] === '' && $data['nim'] === '';
    }
}
