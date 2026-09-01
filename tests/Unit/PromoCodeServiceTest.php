<?php

namespace Tests\Unit;

use App\Services\PromoCodeService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PromoCodeServiceTest extends TestCase
{
    public function test_remember_and_pull_pending_promo_code(): void
    {
        Cache::flush();
        $service = new PromoCodeService();

        $service->rememberPendingCode('111', 91, 'aria2026');

        $this->assertSame('ARIA2026', $service->pullPendingCode('111', 91));
        $this->assertNull($service->pullPendingCode('111', 91));
    }

    public function test_remember_pending_code_ignores_empty_value(): void
    {
        Cache::flush();
        $service = new PromoCodeService();

        $service->rememberPendingCode('111', 91, '   ');

        $this->assertNull($service->pullPendingCode('111', 91));
    }
}
