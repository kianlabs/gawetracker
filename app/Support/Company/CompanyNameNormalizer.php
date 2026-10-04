<?php

namespace App\Support\Company;

/**
 * Normalises company names so the same employer is treated as one entity
 * regardless of how a job board or email phrased it.
 *
 * "PT Tokopedia", "Tokopedia, PT", "tokopedia.com" and "TOKOPEDIA" all collapse
 * to the same key. This is the single source of truth used by both email
 * ingestion and job discovery, so rows coming from different sources can be
 * matched against each other instead of silently duplicating.
 *
 * Pure and side-effect free: safe to unit test against fixtures.
 */
class CompanyNameNormalizer
{
    /**
     * Legal-form prefixes/suffixes that carry no identity. Removed before
     * comparison so "PT" never distinguishes two rows.
     *
     * @var list<string>
     */
    private const LEGAL_FORMS = [
        'pt', 'cv', 'ud', 'pd', 'pt.', 'tbk', 'tbk.', 'ltd', 'ltd.', 'limited',
        'inc', 'inc.', 'llc', 'llc.', 'corp', 'corp.', 'corporation', 'co', 'co.',
        'pte', 'pte.', 'plc', 'plc.', 'gmbh', 'bv', 'bv.', 'nv', 'nv.', 'sa', 'sa.',
        'group', 'holdings', 'holding',
    ];

    /**
     * Common noise tokens stripped from the middle of a name.
     *
     * @var list<string>
     */
    private const NOISE = ['the', 'dan', 'and', '&', 'of', 'untuk', 'for'];

    /**
     * Return the canonical comparison key for a company name.
     *
     * Lower-cased, punctuation removed, legal forms and noise words dropped,
     * whitespace collapsed. Returns an empty string when nothing usable is left
     * (callers should treat that as "unknown company").
     */
    public static function key(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $value = mb_strtolower(trim($name));

        // Strip a URL/path if a domain was pasted in place of a name.
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('#^www\.#', '', $value) ?? $value;
        $value = preg_replace('#/.*$#', '', $value) ?? $value;

        // Drop a trailing TLD only when it is a known company-domain suffix.
        $value = preg_replace('#\.(com|co\.id|co|id|net|org|io|ai|app|tech)$#', '', $value) ?? $value;

        // Replace anything that is not a letter/number with a space.
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $t): bool => $t !== ''
                && ! in_array($t, self::LEGAL_FORMS, true)
                && ! in_array($t, self::NOISE, true),
        ));

        return implode(' ', $tokens);
    }

    /**
     * Whether two company names refer to the same employer.
     */
    public static function same(?string $a, ?string $b): bool
    {
        $ka = self::key($a);
        $kb = self::key($b);

        return $ka !== '' && $ka === $kb;
    }

    /**
     * A human-friendly display name: original casing, trimmed, legal form kept.
     * Used when creating a new company row so we never store an empty label.
     */
    public static function display(?string $name): string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return $name;
    }
}
