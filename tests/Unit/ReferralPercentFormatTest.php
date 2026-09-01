<?php

namespace Tests\Unit;

use App\Models\ReferralSetting;
use Tests\TestCase;

class ReferralPercentFormatTest extends TestCase
{
    public function test_format_percent_does_not_strip_tens_digit(): void
    {
        $this->assertSame('20', ReferralSetting::formatPercentValue(20));
        $this->assertSame('20', ReferralSetting::formatPercentValue(20.0));
        $this->assertSame('20', ReferralSetting::formatPercentValue('20.00'));
        $this->assertSame('10', ReferralSetting::formatPercentValue(10));
        $this->assertSame('100', ReferralSetting::formatPercentValue(100));
        $this->assertSame('50', ReferralSetting::formatPercentValue(50));
        $this->assertSame('12.5', ReferralSetting::formatPercentValue(12.50));
        $this->assertSame('0.5', ReferralSetting::formatPercentValue(0.5));
        $this->assertSame('0', ReferralSetting::formatPercentValue(0));
    }

    public function test_naive_rtrim_would_break_round_percentages(): void
    {
        $this->assertSame('2', rtrim(rtrim((string) 20, '0'), '.'));
        $this->assertSame('1', rtrim(rtrim((string) 10, '0'), '.'));
        $this->assertSame('1', rtrim(rtrim((string) 100, '0'), '.'));
        $this->assertNotSame('2', ReferralSetting::formatPercentValue(20));
        $this->assertNotSame('1', ReferralSetting::formatPercentValue(10));
        $this->assertNotSame('1', ReferralSetting::formatPercentValue(100));
    }

    public function test_commission_uses_percent_out_of_one_hundred(): void
    {
        $this->assertSame(132000.0, ReferralSetting::commissionFromAmount(660000, 20));
        $this->assertSame(13200.0, ReferralSetting::commissionFromAmount(660000, 2));
        $this->assertSame(0.0, ReferralSetting::commissionFromAmount(660000, 0));
        $this->assertSame(0.0, ReferralSetting::commissionFromAmount(0, 20));
    }
}
