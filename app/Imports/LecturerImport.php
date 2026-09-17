<?php

namespace App\Imports;

use App\Enums\LecturerStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk enrolment of dosen. A dosen who already exists is attached to this
 * program studi rather than rejected — teaching in more than one prodi is
 * normal, and the workspace switcher exists for exactly that.
 */
class LecturerImport implements ToCollection, WithHeadingRow
{
    public const COLUMNS = ['nama', 'email', 'nip', 'jabatan', 'kuota_bimbingan'];

    public int $imported = 0;

    public int $attached = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private readonly Department $department) {}

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
                $existing->departments()->syncWithoutDetaching([$this->department->id]);
                $this->attached++;

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
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->assignRole(UserRole::Lecturer->value);

            $lecturer = Lecturer::create([
                'user_id' => $user->id,
                'lecturer_number' => $data['nip'],
                'academic_position' => $data['jabatan'] ?? null,
                'max_supervisions' => $data['kuota_bimbingan'] ?? 10,
                'status' => LecturerStatus::Active,
            ]);

            $lecturer->departments()->attach($this->department->id, ['is_primary' => true]);
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
            'nip' => trim((string) $row->get('nip')),
            'jabatan' => trim((string) $row->get('jabatan')) ?: null,
            'kuota_bimbingan' => $row->get('kuota_bimbingan'),
        ];
    }
}
