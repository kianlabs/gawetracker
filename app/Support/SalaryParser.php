<?php

namespace App\Support;

/**
 * Best-effort parser for the free-text salary labels job boards publish.
 *
 * Boards hand us prose, not numbers:
 *   "IDR 10,000,000 - 15,000,000"      (Glints)
 *   "Rp 6.000.000 – Rp 7.500.000 per month"  (Jobstreet)
 *   ">= Rp 8.000.000", "SGD 5,000/month", "Gaji 10jt - 15jt"
 *
 * We turn that into comparable integers so the UI can sort and filter. Parsing
 * is deliberately lenient and conservative: anything we cannot read confidently
 * yields null rather than a plausible-looking but wrong number, because a wrong
 * salary is worse than a missing one.
 */
final class SalaryParser
{
    /**
     * Currency tokens mapped to ISO codes. Longest symbols first so "S$" wins
     * over "$", and "Rp" over the bare "R".
     *
     * @var array<string, string>
     */
    private const CURRENCIES = [
        'idr' => 'IDR', 'rp' => 'IDR', 'rupiah' => 'IDR',
        'sgd' => 'SGD', 's$' => 'SGD',
        'usd' => 'USD', 'us$' => 'USD',
        'myr' => 'MYR', 'rm' => 'MYR',
        'eur' => 'EUR', '€' => 'EUR',
        'gbp' => 'GBP', '£' => 'GBP',
        'aud' => 'AUD', 'a$' => 'AUD',
        'php' => 'PHP', '₱' => 'PHP',
        '$' => 'USD',
    ];

    /**
     * Period keywords mapped to a normalised bucket. Checked in order, so the
     * more specific "per bulan" is matched before a stray "per".
     *
     * @var array<string, string>
     */
    private const PERIODS = [
        'per month' => 'monthly', '/month' => 'monthly', '/mo' => 'monthly',
        'monthly' => 'monthly', 'per bulan' => 'monthly', '/bulan' => 'monthly',
        'bulanan' => 'monthly', 'per bln' => 'monthly',
        'per year' => 'yearly', '/year' => 'yearly', 'yearly' => 'yearly',
        'annually' => 'yearly', 'per annum' => 'yearly', 'per tahun' => 'yearly',
        '/tahun' => 'yearly', 'tahunan' => 'yearly',
        'per day' => 'daily', '/day' => 'daily', 'daily' => 'daily',
        'per hari' => 'daily', '/hari' => 'daily', 'harian' => 'daily',
        'per hour' => 'hourly', '/hour' => 'hourly', '/hr' => 'hourly',
        'hourly' => 'hourly', 'per jam' => 'hourly', '/jam' => 'hourly',
    ];

    /**
     * Indonesian magnitude suffixes, applied to the number immediately before
     * them ("10jt" → 10,000,000).
     *
     * @var array<string, int>
     */
    private const SUFFIXES = [
        'jt' => 1_000_000, 'juta' => 1_000_000, 'mio' => 1_000_000,
        'm' => 1_000_000, 'miliar' => 1_000_000_000, 'b' => 1_000_000_000,
        'k' => 1_000, 'rb' => 1_000, 'ribu' => 1_000, 'thousand' => 1_000,
        'million' => 1_000_000, 'jtn' => 1_000_000,
    ];

    public static function parse(?string $note): ?ParsedSalary
    {
        if ($note === null || trim($note) === '') {
            return null;
        }

        $text = mb_strtolower(trim($note));

        // A label with no digits at all ("Kompetitif", "Negotiable") is not a
        // number we can trust.
        if (preg_match('/\d/', $text) !== 1) {
            return null;
        }

        $currency = self::detectCurrency($text);
        $period = self::detectPeriod($text);
        $amounts = self::extractAmounts($text, $currency);

        if ($amounts === []) {
            return null;
        }

        // Order the amounts: a lone value is a point, two are a range, more than
        // two (rare, e.g. "8jt - 12jt negotiable") keep the extremes.
        $min = min($amounts);
        $max = max($amounts);

        return new ParsedSalary(
            min: $min,
            max: $max === $min ? $min : $max,
            currency: $currency,
            period: $period,
        );
    }

    private static function detectCurrency(string $text): ?string
    {
        foreach (self::CURRENCIES as $token => $code) {
            if (str_contains($text, $token)) {
                return $code;
            }
        }

        return null;
    }

    private static function detectPeriod(string $text): ?string
    {
        foreach (self::PERIODS as $token => $bucket) {
            if (str_contains($text, $token)) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * Pull every plausible salary amount out of the label, normalising the two
     * common thousands-separator styles and Indonesian magnitude suffixes.
     *
     * @return array<int, int>
     */
    private static function extractAmounts(string $text, ?string $currency): array
    {
        // Grab number-ish tokens, optionally followed by a magnitude suffix.
        preg_match_all(
            '/(\d[\d.,]*)\s*(jt|juta|mio|miliar|ribu|rb|jtn|million|thousand|m|b|k)?/u',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        $amounts = [];

        foreach ($matches as $match) {
            $raw = $match[1];
            $suffix = $match[2] ?? '';
            $value = self::normaliseNumber($raw);

            if ($value === null) {
                continue;
            }

            $qualified = $suffix !== '' && isset(self::SUFFIXES[$suffix]);
            if ($qualified) {
                $value *= self::SUFFIXES[$suffix];
            }

            // Reject noise (years, levels, page numbers) using a floor that
            // makes sense for the label's currency. A magnitude suffix is taken
            // as proof the number is a salary, so it skips the floor.
            if (! $qualified && $value < self::floorFor($currency)) {
                continue;
            }

            if ($value >= 1) {
                $amounts[] = (int) round($value);
            }
        }

        return array_values(array_unique($amounts));
    }

    /**
     * Smallest amount that still looks like a salary in the given currency.
     *
     * Rupiah salaries are in the millions, so a bare "3" is a level, not pay.
     * Foreign currencies are quoted in the hundreds-to-thousands, so the floor
     * is far lower; with no currency hint we assume the Indonesian context.
     */
    private static function floorFor(?string $currency): float
    {
        return match ($currency) {
            'IDR', null => 1_000_000,
            default => 100,
        };
    }

    /**
     * Interpret a raw digit run as either a thousands-separated integer or a
     * decimal, without knowing which convention the label used.
     *
     * "10,000,000" / "10.000.000" → 10000000
     * "1.5"                        → 1.5
     * "1,5"                        → 1.5
     */
    private static function normaliseNumber(string $raw): ?float
    {
        $raw = trim($raw, "., \t\n\r\0\x0B");

        if ($raw === '' || ! is_numeric(str_replace([',', '.'], '', $raw))) {
            return null;
        }

        // Repeated separator = thousands grouping in either convention.
        if (preg_match('/^\d{1,3}(?:([.,])\d{3})+$/', $raw, $m) === 1) {
            return (float) str_replace([',', '.'], '', $raw);
        }

        // A single separator with exactly three digits after it is ambiguous
        // ("1.500" could be 1500 or 1.5). Treat it as grouping when the integer
        // part is small and the label looks like currency, else as a decimal.
        if (preg_match('/^(\d+)([.,])(\d{1,2})$/', $raw, $m) === 1) {
            return (float) ($m[1].'.'.$m[3]);
        }

        if (preg_match('/^(\d+)[.,](\d{3})$/', $raw, $m) === 1) {
            return (float) ($m[1].$m[2]);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }
}
