<?php

namespace Database\Seeders\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds the sample documents the demo account needs. Files are written to the
 * same private-disk layout the application uploads to — `thesis/{student}/{area}`
 * — so the download endpoint authorises them exactly like a real upload.
 */
final readonly class DemoFile
{
    /**
     * @param  array<int, string>  $body
     * @return array{path: string, name: string, size: int}
     */
    public static function pdf(Student $student, string $area, string $name, string $title, array $body): array
    {
        return self::put($student, $area, $name, self::renderPdf($title, $body));
    }

    /**
     * @param  array<int, array<int, string|int>>  $rows
     * @return array{path: string, name: string, size: int}
     */
    public static function csv(Student $student, string $area, string $name, array $rows): array
    {
        $csv = implode("\n", array_map(
            fn (array $row): string => implode(',', array_map(
                fn (string|int $cell): string => '"'.str_replace('"', '""', (string) $cell).'"',
                $row
            )),
            $rows
        ))."\n";

        return self::put($student, $area, $name, $csv);
    }

    /**
     * A drawn-looking signature for the demo accounts, written to the same
     * private path the signature pad uploads to. The curve is derived from the
     * name, so every signer's mark is their own.
     */
    public static function signature(User $user): string
    {
        $path = "signatures/{$user->id}/".str()->random(40).'.png';

        Storage::disk('documents')->put($path, self::renderSignature($user->name));

        return $path;
    }

    /**
     * @return array{path: string, name: string, size: int}
     */
    private static function put(Student $student, string $area, string $name, string $contents): array
    {
        $path = "thesis/{$student->id}/{$area}/".str()->random(40).'.'.pathinfo($name, PATHINFO_EXTENSION);

        Storage::disk('documents')->put($path, $contents);

        return ['path' => $path, 'name' => $name, 'size' => strlen($contents)];
    }

    /**
     * A minimal but genuinely valid single-page PDF, so the sample files open
     * in a real viewer rather than being opaque placeholder bytes.
     *
     * @param  array<int, string>  $body
     */
    private static function renderPdf(string $title, array $body): string
    {
        $stream = "BT\n/F1 16 Tf\n1 0 0 1 60 780 Tm\n(".self::escape($title).") Tj\nET\n";
        $y = 748;

        foreach ($body as $line) {
            $stream .= "BT\n/F1 11 Tf\n1 0 0 1 60 {$y} Tm\n(".self::escape($line).") Tj\nET\n";
            $y -= 18;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $startXref = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$startXref}\n%%EOF\n";
    }

    /**
     * PDF string literals are WinAnsi and must escape their own delimiters.
     */
    private static function escape(string $text): string
    {
        $ascii = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /**
     * One continuous stroke through points seeded by the name's own letters,
     * which is enough to read as handwriting at the size a document shows it.
     */
    private static function renderSignature(string $name): string
    {
        $image = imagecreatetruecolor(320, 110);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        $ink = imagecolorallocate($image, 17, 32, 64);

        if ($transparent === false || $ink === false) {
            throw new RuntimeException('Tidak dapat mengalokasikan warna untuk tanda tangan contoh.');
        }

        imagefill($image, 0, 0, $transparent);
        imagesetthickness($image, 3);

        $letters = array_values(array_filter(
            str_split(strtolower($name)),
            fn (string $char): bool => $char >= 'a' && $char <= 'z',
        ));

        $previous = [24, 70];

        foreach ($letters as $index => $letter) {
            $x = 24 + (int) round($index * (272 / max(count($letters) - 1, 1)));
            // 'a'..'z' becomes a height, so the same name always signs the same.
            $y = 88 - (ord($letter) - 97) * 2 - ($index % 2 === 0 ? 18 : 0);

            imageline($image, $previous[0], $previous[1], $x, $y, $ink);
            $previous = [$x, $y];
        }

        imagesetthickness($image, 2);
        imageline($image, 20, 96, 300, 96, $ink);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();

        return $binary;
    }
}
