<?php

namespace Tests\Unit;

use App\Http\Controllers\MarzbanPannelController;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class MarzbanExpireNormalizeTest extends TestCase
{
    private function normalize($expireRaw): int
    {
        $method = new ReflectionMethod(MarzbanPannelController::class, 'normalizeExpireTimestamp');
        $method->setAccessible(true);

        return $method->invoke(new MarzbanPannelController(), $expireRaw);
    }

    public function test_parses_unix_timestamp_seconds(): void
    {
        $ts = Carbon::now('UTC')->addDays(20)->timestamp;

        $this->assertSame($ts, $this->normalize($ts));
        $this->assertSame($ts, $this->normalize((string) $ts));
    }

    public function test_parses_unix_timestamp_milliseconds(): void
    {
        $ts = Carbon::now('UTC')->addDays(5)->timestamp;

        $this->assertSame($ts, $this->normalize($ts * 1000));
    }

    public function test_parses_pasarguard_iso_datetime_string(): void
    {
        $future = Carbon::now('UTC')->addDays(15)->startOfSecond();
        $past = Carbon::now('UTC')->subDays(15)->startOfSecond();

        $this->assertSame(
            $future->timestamp,
            $this->normalize($future->toIso8601String())
        );
        $this->assertSame(
            $past->timestamp,
            $this->normalize($past->utc()->format('Y-m-d\TH:i:s\Z'))
        );
    }

    public function test_does_not_treat_iso_year_as_epoch_timestamp(): void
    {
        // Bug regression: (int)"2026-09-01T12:00:00+00:00" === 2026
        $iso = '2026-09-01T12:00:00+00:00';
        $normalized = $this->normalize($iso);

        $this->assertNotSame(2026, $normalized);
        $this->assertSame(Carbon::parse($iso)->timestamp, $normalized);
        $this->assertTrue($normalized > 1_000_000_000);
    }

    public function test_returns_zero_for_unlimited_or_empty(): void
    {
        $this->assertSame(0, $this->normalize(null));
        $this->assertSame(0, $this->normalize(0));
        $this->assertSame(0, $this->normalize('0'));
        $this->assertSame(0, $this->normalize(''));
        $this->assertSame(0, $this->normalize('null'));
    }
}
