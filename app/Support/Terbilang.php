<?php

namespace App\Support;

/**
 * Numbers and dates spelled out in Bahasa Indonesia.
 *
 * Official documents state figures twice — once as digits and once in words —
 * so the words cannot be altered without the digits disagreeing.
 */
final class Terbilang
{
    private const UNITS = [
        'nol', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /**
     * A whole number in words: 2026 becomes "dua ribu dua puluh enam".
     */
    public static function integer(int $number): string
    {
        if ($number < 0) {
            return 'minus '.self::integer(-$number);
        }

        return trim(self::spell($number));
    }

    /**
     * A score in words, reading the decimals after "koma".
     *
     * 84.50 becomes "delapan puluh empat koma lima puluh"; 84.05 becomes
     * "delapan puluh empat koma nol lima"; a whole number keeps no decimals.
     */
    public static function decimal(float $number, int $places = 2): string
    {
        $rounded = round($number, $places);
        $whole = (int) floor(abs($rounded));
        $fraction = (int) round((abs($rounded) - $whole) * (10 ** $places));

        $words = ($rounded < 0 ? 'minus ' : '').self::integer($whole);

        if ($fraction === 0) {
            return $words;
        }

        // A leading zero has to be spoken, or "nol lima" reads as "lima".
        $spoken = $fraction < 10 && $places > 1
            ? 'nol '.self::integer($fraction)
            : self::integer($fraction);

        return $words.' koma '.$spoken;
    }

    private static function spell(int $number): string
    {
        return match (true) {
            $number < 12 => self::UNITS[$number],
            $number < 20 => self::spell($number - 10).' belas',
            $number < 100 => self::spell(intdiv($number, 10)).' puluh'.self::remainder($number, 10),
            $number < 200 => 'seratus'.self::remainder($number, 100),
            $number < 1000 => self::spell(intdiv($number, 100)).' ratus'.self::remainder($number, 100),
            $number < 2000 => 'seribu'.self::remainder($number, 1000),
            $number < 1_000_000 => self::spell(intdiv($number, 1000)).' ribu'.self::remainder($number, 1000),
            $number < 1_000_000_000 => self::spell(intdiv($number, 1_000_000)).' juta'.self::remainder($number, 1_000_000),
            default => self::spell(intdiv($number, 1_000_000_000)).' miliar'.self::remainder($number, 1_000_000_000),
        };
    }

    private static function remainder(int $number, int $divisor): string
    {
        $rest = $number % $divisor;

        return $rest === 0 ? '' : ' '.self::spell($rest);
    }
}
