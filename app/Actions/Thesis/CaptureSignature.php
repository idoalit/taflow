<?php

namespace App\Actions\Thesis;

use App\Enums\SignatureType;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a signature, drawn or uploaded, on the private disk.
 *
 * Signatures are never public: they are served only inside the documents that
 * carry them.
 */
final readonly class CaptureSignature
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function fromDataUri(User $user, string $dataUri, Request $request): Signature
    {
        $binary = $this->decode($dataUri);

        return $this->store($user, $binary, SignatureType::Drawn, $request);
    }

    public function fromUpload(User $user, UploadedFile $file, Request $request): Signature
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages([
                'signature' => 'Berkas tanda tangan tidak dapat dibaca sebagai gambar.',
            ]);
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();

        return $this->store($user, $binary, SignatureType::Uploaded, $request);
    }

    private function decode(string $dataUri): string
    {
        if (! preg_match('#^data:image/(png|jpe?g);base64,#', $dataUri)) {
            throw ValidationException::withMessages([
                'signature' => 'Tanda tangan harus berupa gambar PNG atau JPEG.',
            ]);
        }

        $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'signature' => 'Tanda tangan tidak dapat dibaca.',
            ]);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'signature' => 'Ukuran tanda tangan melebihi 2MB.',
            ]);
        }

        return $binary;
    }

    private function store(User $user, string $binary, SignatureType $type, Request $request): Signature
    {
        $path = "signatures/{$user->id}/".Str::uuid()->toString().'.png';

        Storage::disk('local')->put($path, $binary);

        return Signature::create([
            'user_id' => $user->id,
            'type' => $type,
            'path' => $path,
            'signed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
