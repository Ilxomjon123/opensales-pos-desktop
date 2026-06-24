<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Translit;
use PHPUnit\Framework\TestCase;

final class TranslitTest extends TestCase
{
    public function test_variants_lowercase_latin_input(): void
    {
        $this->assertSame(['ishon', 'ишон'], Translit::variants('Ishon'));
    }

    public function test_variants_lowercase_cyrillic_input(): void
    {
        $this->assertSame(['ишон', 'ishon'], Translit::variants('ИШОН'));
    }

    public function test_variants_empty_for_blank_term(): void
    {
        $this->assertSame([], Translit::variants('   '));
    }

    public function test_to_cyrillic_handles_uzbek_digraphs(): void
    {
        $this->assertSame('шч', Translit::toCyrillic('shch'));
        $this->assertSame('чой', Translit::toCyrillic('choy'));
    }

    public function test_to_latin_converts_cyrillic(): void
    {
        $this->assertSame('ishonch', Translit::toLatin('ишонч'));
    }
}
