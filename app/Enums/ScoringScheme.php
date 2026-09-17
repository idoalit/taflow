<?php

namespace App\Enums;

/**
 * How a rubric turns marks into a verdict.
 */
enum ScoringScheme: string
{
    case Weighted = 'weighted';
    case BlockAdditive = 'block_additive';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weighted => 'Berbobot (persentase terbobot)',
            self::BlockAdditive => 'Aditif per Blok (jumlah skor, total 100)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weighted => 'Tiap kriteria punya bobot dan skalanya sendiri. '
                .'Nilai akhir adalah persentase terbobot.',
            self::BlockAdditive => 'Kriteria dikelompokkan dalam blok, dan skor maksimalnya '
                .'berjumlah 100. Nilai akhir adalah jumlah seluruh skor.',
        };
    }
}
