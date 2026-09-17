<?php

namespace App\Concerns;

use Carbon\CarbonInterface;

/**
 * Date formatting for the interface, which is entirely in Bahasa Indonesia.
 * The Carbon locale is set application-wide in AppServiceProvider, so these
 * formats only have to describe the shape used in the design.
 */
trait FormatsIndonesianDates
{
    /** "12 Agu 2026" */
    protected function shortDate(?CarbonInterface $date): ?string
    {
        return $date?->isoFormat('D MMM YYYY');
    }

    /** "12 Agustus 2026, 09:40" */
    protected function longDateTime(?CarbonInterface $date): ?string
    {
        return $date?->isoFormat('D MMMM YYYY, HH:mm');
    }

    /** "12 Agu 2026, 09:40" */
    protected function shortDateTime(?CarbonInterface $date): ?string
    {
        return $date?->isoFormat('D MMM YYYY, HH:mm');
    }

    /** "Kamis, 18 September 2026 · 13:00" */
    protected function scheduleLabel(?CarbonInterface $date): ?string
    {
        return $date === null
            ? null
            : $date->isoFormat('dddd, D MMMM YYYY').' · '.$date->format('H:i');
    }

    /** "16 Sep, 09:12" — used inside the guidance chat. */
    protected function messageStamp(?CarbonInterface $date): ?string
    {
        return $date?->isoFormat('D MMM, HH:mm');
    }

    /** "3 hari lalu" */
    protected function relative(?CarbonInterface $date): ?string
    {
        return $date?->diffForHumans();
    }

    protected function humanFileSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }
}
