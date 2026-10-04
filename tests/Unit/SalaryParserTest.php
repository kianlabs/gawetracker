<?php

namespace Tests\Unit;

use App\Support\ParsedSalary;
use App\Support\SalaryParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalaryParserTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?int, 2: ?int, 3: ?string, 4: ?string}>
     */
    public static function labels(): array
    {
        return [
            'glints comma thousands' => ['IDR 10,000,000 - 15,000,000', 10_000_000, 15_000_000, 'IDR', null],
            'jobstreet dotted thousands with period' => ['Rp 6.000.000 – Rp 7.500.000 per month', 6_000_000, 7_500_000, 'IDR', 'monthly'],
            'single lower bound' => ['>= Rp 8.000.000', 8_000_000, 8_000_000, 'IDR', null],
            'indonesian juta suffix' => ['Gaji 10jt - 15jt per bulan', 10_000_000, 15_000_000, null, 'monthly'],
            'indonesian juta word' => ['Rp 5 juta - 7 juta', 5_000_000, 7_000_000, 'IDR', null],
            'sgd with slash month' => ['SGD 5,000/month', 5_000, 5_000, 'SGD', 'monthly'],
            'usd range' => ['USD 80,000 - 120,000 per year', 80_000, 120_000, 'USD', 'yearly'],
            'no digits is unparseable' => ['Kompetitif', null, null, null, null],
            'empty is unparseable' => ['', null, null, null, null],
            'null is unparseable' => [null, null, null, null, null],
            'noise below threshold ignored' => ['Level 3, gaji negotiable', null, null, null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('labels')]
    public function test_it_parses_salary_labels(?string $label, ?int $min, ?int $max, ?string $currency, ?string $period): void
    {
        $parsed = SalaryParser::parse($label);

        if ($min === null && $max === null && $currency === null && $period === null) {
            $this->assertTrue($parsed === null || $parsed->isEmpty());

            return;
        }

        $this->assertInstanceOf(ParsedSalary::class, $parsed);
        $this->assertSame($min, $parsed->min);
        $this->assertSame($max, $parsed->max);
        $this->assertSame($currency, $parsed->currency);
        $this->assertSame($period, $parsed->period);
    }

    public function test_to_columns_maps_every_field(): void
    {
        $parsed = SalaryParser::parse('Rp 6.000.000 – Rp 7.500.000 per month');

        $this->assertSame([
            'salary_min' => 6_000_000,
            'salary_max' => 7_500_000,
            'salary_currency' => 'IDR',
            'salary_period' => 'monthly',
        ], $parsed->toColumns());
    }

    public function test_it_orders_reversed_amounts(): void
    {
        $parsed = SalaryParser::parse('Rp 15.000.000 - 10.000.000');

        $this->assertSame(10_000_000, $parsed->min);
        $this->assertSame(15_000_000, $parsed->max);
    }
}
