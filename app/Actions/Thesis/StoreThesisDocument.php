<?php

namespace App\Actions\Thesis;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Uploaded documents are namespaced by student so that DocumentController can
 * authorise a download from the path alone.
 */
final readonly class StoreThesisDocument
{
    /**
     * @return array{path: string, name: string, size: int}
     */
    public function __invoke(UploadedFile $file, Student $student, string $area): array
    {
        $path = $file->store("thesis/{$student->id}/{$area}", 'local');

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan dokumen yang diunggah.');
        }

        $size = $file->getSize();

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $size === false ? 0 : $size,
        ];
    }
}
