<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Http\Controllers\DocumentController;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A stored file rendered as the design's file row: name, size, upload date and
 * a download link.
 *
 * @property array{name: string|null, path: string|null, size: int|null, uploaded_at: CarbonInterface|null} $resource
 */
class DocumentResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(Request $request): ?array
    {
        if (($this->resource['path'] ?? null) === null) {
            return null;
        }

        $path = $this->resource['path'];

        $name = $this->resource['name'] ?? basename($path);
        $exists = Storage::disk('local')->exists($path);

        return [
            'name' => $name,
            'kind' => strtoupper(pathinfo($name, PATHINFO_EXTENSION)) ?: 'FILE',
            'previewable' => $exists && DocumentController::isPreviewable($path),
            'preview_url' => $exists
                ? route('documents.show', ['path' => $path, 'name' => $name, 'preview' => 1])
                : null,
            'size' => $this->humanFileSize($this->resource['size'] ?? null) ?? '—',
            'uploaded_at' => $this->shortDate($this->resource['uploaded_at'] ?? null) ?? '—',
            'url' => $exists ? route('documents.show', ['path' => $path, 'name' => $name]) : '#',
        ];
    }

    /**
     * Build from a model's document columns.
     */
    public static function fromColumns(?string $path, ?string $name, ?int $size, ?CarbonInterface $uploadedAt): ?self
    {
        return $path === null ? null : new self([
            'path' => $path,
            'name' => $name,
            'size' => $size,
            'uploaded_at' => $uploadedAt,
        ]);
    }
}
