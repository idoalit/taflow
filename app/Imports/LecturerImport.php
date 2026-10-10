<?php

namespace App\Imports;

use App\Actions\Academic\AssignLecturer;
use App\Actions\Academic\RegisterLecturer;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk enrolment of dosen. A dosen who already exists is added to this
 * program studi rather than rejected — working in more than one prodi is
 * normal, and the workspace switcher exists for exactly that. It goes through
 * the same door as adding them by hand, so their homebase hears of it.
 */
class LecturerImport implements ToCollection, WithHeadingRow
{
    public const COLUMNS = ['nama', 'email', 'nip', 'jabatan', 'kuota_bimbingan'];

    public int $imported = 0;

    public int $attached = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(
        private readonly Department $department,
        private readonly User $actor,
        private readonly RegisterLecturer $register = new RegisterLecturer,
        private readonly AssignLecturer $assign = new AssignLecturer,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $data = $this->normalise($row);

            if ($data['nama'] === '' && $data['email'] === '' && $data['nip'] === '') {
                continue;
            }

            $existing = Lecturer::where('lecturer_number', $data['nip'])->first();

            if ($existing !== null) {
                // A dosen who is here already is not counted a second time.
                if ($this->assign->handle($existing, $this->department, $this->actor)) {
                    $this->attached++;
                }

                continue;
            }

            $validator = Validator::make($data, [
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'nip' => ['required', 'string', 'max:50'],
                'jabatan' => ['nullable', 'string', 'max:100'],
                'kuota_bimbingan' => ['nullable', 'integer', 'min:0', 'max:50'],
            ], [
                'email.unique' => 'surel sudah terdaftar',
            ]);

            if ($validator->fails()) {
                $this->errors[] = "Baris {$line}: ".implode(', ', $validator->errors()->all());

                continue;
            }

            $valid = $validator->validated();

            $this->register->handle($this->department, [
                'name' => $valid['nama'],
                'email' => $valid['email'],
                'lecturer_number' => $valid['nip'],
                'academic_position' => $valid['jabatan'] ?? null,
                'max_supervisions' => $valid['kuota_bimbingan'] ?? null,
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
            'nip' => trim((string) $row->get('nip')),
            'jabatan' => trim((string) $row->get('jabatan')) ?: null,
            'kuota_bimbingan' => $row->get('kuota_bimbingan'),
        ];
    }
}
