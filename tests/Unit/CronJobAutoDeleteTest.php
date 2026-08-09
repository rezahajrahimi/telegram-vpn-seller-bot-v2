<?php

namespace Tests\Unit;

use App\Http\Controllers\CronJobController;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class CronJobAutoDeleteTest extends TestCase
{
    private function shouldAutoDelete(array $user): bool
    {
        $method = new ReflectionMethod(CronJobController::class, 'shouldAutoDeletePanelUser');
        $method->setAccessible(true);

        return $method->invoke(new CronJobController(), $user);
    }

    public function test_does_not_delete_active_marzban_user_with_unlimited_data(): void
    {
        $expireTs = Carbon::now('UTC')->addDays(20)->timestamp;

        $this->assertFalse($this->shouldAutoDelete([
            'uuid' => 'user-1',
            'current_usage_GB' => 5.0,
            'usage_limit_GB' => 0,
            'package_days' => 20,
            'start_date' => Carbon::now('UTC')->toDateString(),
            'expire_timestamp' => $expireTs,
        ]));
    }

    public function test_does_not_delete_recently_expired_marzban_user(): void
    {
        $expireTs = Carbon::now('UTC')->subDays(3)->timestamp;

        $this->assertFalse($this->shouldAutoDelete([
            'uuid' => 'user-2',
            'current_usage_GB' => 10.0,
            'usage_limit_GB' => 20.0,
            'package_days' => 0,
            'start_date' => Carbon::now('UTC')->toDateString(),
            'expire_timestamp' => $expireTs,
        ]));
    }

    public function test_deletes_marzban_user_expired_more_than_ten_days_ago(): void
    {
        $expireTs = Carbon::now('UTC')->subDays(15)->timestamp;

        $this->assertTrue($this->shouldAutoDelete([
            'uuid' => 'user-3',
            'current_usage_GB' => 1.0,
            'usage_limit_GB' => 0,
            'package_days' => 0,
            'start_date' => Carbon::now('UTC')->toDateString(),
            'expire_timestamp' => $expireTs,
        ]));
    }

    public function test_does_not_delete_active_user_when_volume_limit_is_zero(): void
    {
        $this->assertFalse($this->shouldAutoDelete([
            'uuid' => 'user-4',
            'current_usage_GB' => 12.0,
            'usage_limit_GB' => 0,
            'package_days' => 15,
            'start_date' => Carbon::now('UTC')->toDateString(),
        ]));
    }

    public function test_deletes_user_without_expiry_metadata_when_volume_is_exhausted(): void
    {
        $this->assertTrue($this->shouldAutoDelete([
            'uuid' => 'user-5',
            'current_usage_GB' => 10.0,
            'usage_limit_GB' => 10.0,
            'package_days' => 0,
        ]));
    }

    public function test_does_not_delete_when_expire_timestamp_is_misparsed_year_bug(): void
    {
        // Guard against the old Pasarguard bug: (int)"2026-..." === 2026.
        // A real future expire must not look like epoch year 1970.
        $futureTs = Carbon::now('UTC')->addDays(30)->timestamp;

        $this->assertFalse($this->shouldAutoDelete([
            'uuid' => 'user-6',
            'current_usage_GB' => 1.0,
            'usage_limit_GB' => 50.0,
            'package_days' => 0,
            'expire_timestamp' => $futureTs,
        ]));
    }
}
