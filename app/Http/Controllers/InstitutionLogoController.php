<?php

namespace App\Http\Controllers;

use App\Support\SystemSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The logo the superadmin uploaded, served as an image so the browser can use
 * it as the tab icon.
 *
 * Public by design: the same logo is already on the sign-in screen. The URL
 * carries a version taken from the stored path, which changes with every
 * upload, so the image can be cached for as long as the browser likes.
 */
class InstitutionLogoController extends Controller
{
    public function __invoke(SystemSettings $settings): StreamedResponse
    {
        $path = $settings->uploadedLogoPath();
        $disk = Storage::disk('documents');

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Content-Type' => SystemSettings::logoMimeType($path),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
