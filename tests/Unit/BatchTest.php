<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BatchTest extends TestCase
{
    public function testBudgetIsFractionOfExecutionLimit(): void
    {
        self::assertSame(21.0, ModernFormats_Batch::time_budget(30));
        self::assertSame(28.0, ModernFormats_Batch::time_budget(40));
    }

    public function testBudgetHasAFloor(): void
    {
        // A tiny limit must still leave room for at least one photo.
        self::assertSame(10.0, ModernFormats_Batch::time_budget(5));
    }

    public function testGenerousLimitIsCappedAtCeiling(): void
    {
        // A long limit must not let a chunk outlast a gateway timeout.
        self::assertSame(30.0, ModernFormats_Batch::time_budget(60));
        self::assertSame(30.0, ModernFormats_Batch::time_budget(300));
    }

    public function testUnlimitedExecutionTimeUsesTheCeiling(): void
    {
        // 0 (or negative) means no PHP limit; keep requests short anyway.
        self::assertSame(30.0, ModernFormats_Batch::time_budget(0));
        self::assertSame(30.0, ModernFormats_Batch::time_budget(-1));
    }
}
