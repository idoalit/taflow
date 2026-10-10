<?php

namespace App\Imports;

use App\Actions\Academic\EnrollStudent;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
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

    public function __construct(
        private readonly Department $department,
        private readonly EnrollStudent $enroll = new EnrollStudent,
    ) {}

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

            $row = $validator->validated();

            $this->enroll->handle($this->department, [
                'name' => $row['nama'],
                'email' => $row['email'],
                'student_number' => $row['nim'],
                'enrollment_year' => $row['angkatan'],
            ]);
            $this->imported++;
        }
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
