<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Types the browser can render safely in place. Anything outside this list
     * is sent as an attachment even when a preview is asked for: serving an
     * uploaded .html or .svg inline would run its script on our own origin.
     *
     * @var array<string, string>
     */
    private const INLINE_TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
        'csv' => 'text/plain',
    ];

    /**
     * Stream an uploaded document. Files live on the private disk, so every
     * request is checked against the requesting user's visibility of the owning
     * student before anything is served.
     */
    public function show(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path');

        abort_unless($this->isOwnedPath($path), 404);
        abort_unless(Storage::disk('documents')->exists($path), 404);

        $student = Student::find($this->studentIdFrom($path));

        abort_if($student === null, 404);
        abort_unless($student->isVisibleTo($request->user()), 403);

        $name = $this->downloadName($request, $path);
        $mime = self::INLINE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($request->boolean('preview') && $mime !== null) {
            return Storage::disk('documents')->response($path, $name, [
                'Content-Type' => $mime,
                // Belt and braces: even for the allowed types, forbid scripts
                // and plugins so a crafted file cannot act on our origin.
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'none'; script-src 'none'; style-src 'unsafe-inline'",
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk('documents')->download($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Whether a file of this name can be shown in place rather than downloaded.
     */
    public static function isPreviewable(string $path): bool
    {
        return isset(self::INLINE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))]);
    }

    /**
     * Paths are written as `thesis/{studentId}/{area}/{file}`; anything else is
     * not ours to serve.
     */
    private function isOwnedPath(string $path): bool
    {
        return preg_match('#^thesis/\d+/[a-z-]+/[^/]+$#', $path) === 1;
    }

    private function studentIdFrom(string $path): int
    {
        return (int) explode('/', $path)[1];
    }

    /**
     * Uploads are stored under a hashed name, so the original filename travels
     * as a query parameter. It is reduced to a bare basename before use so it
     * cannot introduce a path or header of its own.
     */
    private function downloadName(Request $request, string $path): string
    {
        $requested = basename(str_replace(['\\', "\r", "\n", '"'], '', (string) $request->query('name')));

        return $requested === '' ? basename($path) : $requested;
    }
}
