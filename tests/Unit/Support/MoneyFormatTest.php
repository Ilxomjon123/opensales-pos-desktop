<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\Currency;
use App\Support\Money;
use Tests\TestCase;

final class MoneyFormatTest extends TestCase
{
    public function test_formats_grouped_without_symbol(): void
    {
        $this->assertSame('1 200 000', Money::format(1_200_000, Currency::UZS));
        $this->assertSame('0', Money::format(0, Currency::RUB));
    }

    public function test_rounds_float_amounts(): void
    {
        $this->assertSame('24 286', Money::format(24_285.71, Currency::UZS));
    }

    public function test_appends_localized_symbol(): void
    {
        app()->setLocale('uz');
        $this->assertSame("50 000 so'm", Money::formatWithSymbol(50_000, Currency::UZS));
        $this->assertSame('50 000 ₽', Money::formatWithSymbol(50_000, Currency::RUB));
    }
}
