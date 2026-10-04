<?php

namespace App\Support;

/**
 * Structured result of parsing a free-text salary label.
 *
 * Amounts are plain integers in the label's own currency (we do not convert
 * between currencies). A single-sided label ("dari Rp 8.000.000") leaves the
 * other bound null.
 */
final class ParsedSalary
{
    public function __construct(
        public readonly ?int $min,
        public readonly ?int $max,
        public readonly ?string $currency,
        public readonly ?string $period,   // 'monthly' | 'yearly' | 'daily' | 'hourly' | null
    ) {}

    /**
     * True when nothing usable could be extracted.
     */
    public function isEmpty(): bool
    {
        return $this->min === null && $this->max === null;
    }

    /**
     * Persistable columns for a job_postings row.
     *
     * @return array<string, mixed>
     */
    public function toColumns(): array
    {
        return [
            'salary_min' => $this->min,
            'salary_max' => $this->max,
            'salary_currency' => $this->currency,
            'salary_period' => $this->period,
        ];
    }
}
