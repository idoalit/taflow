<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AssignLecturer;
use App\Actions\Academic\ReleaseLecturer;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Whether a dosen is in the program studi the workspace names. The dosen's
 * own record is shared by every prodi they work in; this is the one part of
 * it that is this prodi's alone to change.
 */
class LecturerMembershipController extends Controller
{
    use ScopesToDepartment;

    /**
     * Adds a dosen who already exists — registered by another program studi —
     * to this one.
     */
    public function store(Request $request, Lecturer $lecturer, AssignLecturer $assign): RedirectResponse
    {
        Gate::authorize('create', Lecturer::class);

        $department = $this->department($request);

        $added = $assign->handle($lecturer, $department, $request->user(), $request);

        return to_route('admin.lecturers.show', $lecturer)->with(
            'success',
            $added
                ? "{$lecturer->user->name} ditambahkan ke {$department->name}."
                : "{$lecturer->user->name} sudah menjadi dosen {$department->name}.",
        );
    }

    public function destroy(Request $request, Lecturer $lecturer, ReleaseLecturer $release): RedirectResponse
    {
        $department = $this->department($request);
        abort_unless($lecturer->departments()->whereKey($department->id)->exists(), 404);

        $lecturer->load('departments');
        Gate::authorize('release', [$lecturer, $department]);

        $release->handle($lecturer, $department, $request->user(), $request);

        return to_route('admin.lecturers.index')
            ->with('success', "{$lecturer->user->name} dilepas dari {$department->name}.");
    }
}
