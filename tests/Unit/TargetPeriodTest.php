<?php

namespace Tests\Unit;

use App\Services\TargetStats;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TargetPeriodTest extends TestCase
{
    public function test_a_week_runs_monday_to_sunday_and_a_month_covers_the_calendar_month(): void
    {
        $ref = Carbon::create(2026, 10, 29, 13, 0);   // Kamis

        [$from, $to] = TargetStats::range('week', $ref);
        $this->assertSame('2026-10-26', $from->toDateString());
        $this->assertSame('2026-11-01', $to->toDateString());

        [$from, $to] = TargetStats::range('month', $ref);
        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-10-31', $to->toDateString());
    }
}
