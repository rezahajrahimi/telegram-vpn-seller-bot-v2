<?php

namespace Tests\Unit;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeUsagePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usages_are_paginated_and_capped(): void
    {
        $promo = PromoCode::create([
            'code' => 'ARIA2026',
            'type' => 'percent',
            'value' => 20,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 23; $i++) {
            PromoCodeUsage::create([
                'promo_code_id' => $promo->id,
                'account_id' => (string) (1000 + $i),
                'discount_amount' => 90000,
                'applied_at' => Carbon::now()->subMinutes(23 - $i),
            ]);
        }

        $page1 = PromoCodeUsage::paginateForPromo($promo->id, 1, 10);
        $this->assertSame(10, $page1->count());
        $this->assertSame(23, $page1->total());
        $this->assertSame(3, $page1->lastPage());

        $capped = PromoCodeUsage::paginateForPromo($promo->id, 1, 500);
        $this->assertSame(23, $capped->count());
        $this->assertSame(50, $capped->perPage());

        $minPage = PromoCodeUsage::paginateForPromo($promo->id, 0, 2);
        $this->assertSame(1, $minPage->currentPage());
        $this->assertSame(5, $minPage->perPage());
    }
}
