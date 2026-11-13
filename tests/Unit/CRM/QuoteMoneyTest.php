<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Services\Quotes\QuoteService;
use PHPUnit\Framework\TestCase;

final class QuoteMoneyTest extends TestCase
{
    public function test_decimal_quantity_is_normalized_without_float_arithmetic(): void
    {
        self::assertSame('1.250', QuoteService::normalizeQuantity('1,25'));
        self::assertSame(1250, QuoteService::calculateLineTotalCents('1.25', 1000));
    }

    public function test_line_total_rounds_half_cent_up_in_integer_arithmetic(): void
    {
        self::assertSame(333, QuoteService::calculateLineTotalCents('0.001', 333000));
        self::assertSame(334, QuoteService::calculateLineTotalCents('0.001', 333500));
    }
}
