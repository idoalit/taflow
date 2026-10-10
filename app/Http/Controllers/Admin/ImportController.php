<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Imports\LecturerImport;
use App\Imports\StudentImport;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    use ScopesToDepartment;

    private const TARGETS = ['students', 'lecturers'];

    public function index(Request $request): Response
    {
        Gate::authorize('import', Student::class);

        return Inertia::render('admin/import/index', [
            'department' => $this->department($request)->name,
            'targets' => [
                ['value' => 'students', 'label' => 'Mahasiswa', 'columns' => StudentImport::COLUMNS],
                ['value' => 'lecturers', 'label' => 'Dosen', 'columns' => LecturerImport::COLUMNS],
            ],
        ]);
    }

    /**
     * The template is generated from the importer's own column list, so the two
     * cannot drift apart.
     */
    public function template(Request $request, string $target): BinaryFileResponse
    {
        Gate::authorize('import', Student::class);
        abort_unless(in_array($target, self::TARGETS, true), 404);

        $columns = $target === 'students' ? StudentImport::COLUMNS : LecturerImport::COLUMNS;
        $name = $target === 'students' ? 'Template_Import_Mahasiswa' : 'Template_Import_Dosen';

        return Excel::download(
            new class($columns) implements FromArray
            {
                /** @param array<int, string> $columns */
                public function __construct(private readonly array $columns) {}

                /** @return array<int, array<int, string>> */
                public function array(): array
                {
                    return [$this->columns];
                }
            },
            "{$name}.xlsx",
        );
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('import', Student::class);

        $department = $this->department($request);

        $validated = $request->validate([
            'target' => ['required', 'in:students,lecturers'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'file.mimes' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
        ], [
            'target' => 'jenis data',
            'file' => 'berkas',
        ]);

        $import = $validated['target'] === 'students'
            ? new StudentImport($department)
            : new LecturerImport($department, $request->user());

        Excel::import($import, $request->file('file'));

        $attached = $import instanceof LecturerImport ? $import->attached : 0;

        return back()->with('importResult', [
            'imported' => $import->imported,
            'attached' => $attached,
            'failed' => count($import->errors),
            'errors' => array_slice($import->errors, 0, 20),
        ]);
    }
}
