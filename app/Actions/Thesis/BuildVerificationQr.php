<?php

namespace App\Actions\Thesis;

use App\Models\ThesisExam;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

/**
 * The QR a released document carries.
 *
 * The token is minted at release and nowhere else, so a code cannot exist for
 * a result that has not been published. It is a capability: whoever holds the
 * printed page can check the document is genuine without an account.
 */
final readonly class BuildVerificationQr
{
    public function tokenFor(ThesisExam $exam): ?string
    {
        if (! $exam->isReleased()) {
            return null;
        }

        if ($exam->verification_token === null) {
            $exam->forceFill(['verification_token' => Str::uuid()->toString()])->save();
        }

        return $exam->verification_token;
    }

    public function urlFor(ThesisExam $exam): ?string
    {
        $token = $this->tokenFor($exam);

        return $token === null ? null : route('documents.verify', ['token' => $token]);
    }

    /**
     * A data URI, which is the only form a PDF renderer can embed without
     * reaching back out over the network.
     */
    public function dataUriFor(ThesisExam $exam): ?string
    {
        $url = $this->urlFor($exam);

        if ($url === null) {
            return null;
        }

        $result = new PngWriter()->write(
            new QrCode(
                data: $url,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 220,
                margin: 8,
            ),
        );

        return $result->getDataUri();
    }
}
