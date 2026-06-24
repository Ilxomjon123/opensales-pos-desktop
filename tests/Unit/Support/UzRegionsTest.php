<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\UzRegions;
use PHPUnit\Framework\TestCase;

final class UzRegionsTest extends TestCase
{
    public function test_matches_district_with_modifier_letter_apostrophe(): void
    {
        // Nominatim `ʻ` (U+02BB, Lm toifa) ishlatadi, kanonik nom ASCII `'`.
        $result = UzRegions::match(
            'Namangan Viloyati',
            "To\u{02BB}raqo\u{02BB}rg\u{02BB}on tumani",
        );

        $this->assertSame('Namangan viloyati', $result['region']);
        $this->assertSame("To'raqo'rg'on tumani", $result['district']);
    }

    public function test_matches_district_with_ascii_apostrophe(): void
    {
        $result = UzRegions::match('Namangan viloyati', "To'raqo'rg'on tumani");

        $this->assertSame('Namangan viloyati', $result['region']);
        $this->assertSame("To'raqo'rg'on tumani", $result['district']);
    }

    public function test_matches_region_only(): void
    {
        $result = UzRegions::match('Namangan Viloyati', null);

        $this->assertSame('Namangan viloyati', $result['region']);
        $this->assertNull($result['district']);
    }

    public function test_returns_null_for_unknown(): void
    {
        $result = UzRegions::match(null, null);

        $this->assertNull($result['region']);
        $this->assertNull($result['district']);
    }
}
