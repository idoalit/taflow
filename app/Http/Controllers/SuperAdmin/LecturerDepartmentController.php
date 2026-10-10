<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Where a dosen belongs: every program studi they work in, and which one of
 * them is their homebase. A program studi can add a dosen to itself and let a
 * borrowed one go; moving a homebase is decided here, above any one prodi.
 */
class LecturerDepartmentController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $lecturer = $user->lecturer;
        abort_if($lecturer === null, 404);

        $validated = $request->validate([
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', Rule::exists(Department::class, 'id')],
            'homebase_id' => ['required', 'integer', Rule::in($request->input('department_ids', []))],
        ], [
            'department_ids.required' => 'Seorang dosen harus berada di sedikitnya satu program studi.',
            'homebase_id.required' => 'Pilih program studi yang menjadi homebase.',
            'homebase_id.in' => 'Homebase harus salah satu program studi yang dipilih.',
        ], [
            'department_ids' => 'program studi',
            'homebase_id' => 'homebase',
        ]);

        $kept = array_map(intval(...), $validated['department_ids']);
        $homebase = (int) $validated['homebase_id'];

        // A prodi cannot be taken away while the dosen still sits on the panel
        // of its mahasiswa: the workspace they do that in would go with it.
        $stillNeeded = $lecturer->departments()->whereKeyNot($kept)->get()
            ->filter(fn (Department $department): bool => $lecturer->panelSeatsIn($department) > 0);

        if ($stillNeeded->isNotEmpty()) {
            throw ValidationException::withMessages([
                'department_ids' => 'Dosen ini masih menjadi pembimbing atau penguji mahasiswa di '
                    .$stillNeeded->pluck('name')->join(', ', ' dan ')
                    .'. Ganti dulu penugasannya sebelum melepasnya dari sana.',
            ]);
        }

        $wanted = collect($kept)->mapWithKeys(fn (int $id): array => [$id => $id === $homebase]);
        $lecturer->load('departments');
        $currentHomebase = $lecturer->homebase();
        $current = $lecturer->departments
            ->mapWithKeys(fn (Department $department): array => [$department->id => $department->is($currentHomebase)]);

        // Compared by hand: sync() touches the timestamp of every row it
        // keeps, so its own report says "updated" even when nothing was.
        if ($wanted->sortKeys()->all() !== $current->sortKeys()->all()) {
            $lecturer->departments()->sync(
                $wanted->map(fn (bool $isHomebase): array => ['is_primary' => $isHomebase])->all(),
            );
            $lecturer->load('departments');

            AuditLog::record(
                $user,
                AuditAction::LecturerDepartmentsUpdated,
                $request->user(),
                changes: [
                    'name' => $user->name,
                    'lecturer_number' => $lecturer->lecturer_number,
                    'departments' => $lecturer->departments->sortBy('name')->pluck('name')->values()->all(),
                    'homebase' => $lecturer->homebase()?->name,
                ],
                request: $request,
            );
        }

        return back()->with('success', 'Program studi dosen diperbarui.');
    }
}
