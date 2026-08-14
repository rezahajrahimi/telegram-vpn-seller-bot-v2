<?php

namespace Tests\Unit;

use App\Http\Controllers\CustomTextController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SwapPayCustomTextSeedTest extends TestCase
{
    use RefreshDatabase;
    public function test_swappay_button_and_reply_keys_exist_in_seed_data(): void
    {
        $controller = new CustomTextController();
        $method = new ReflectionMethod(CustomTextController::class, 'getSeedData');
        $method->setAccessible(true);
        $keys = array_column($method->invoke($controller), 'key');

        $this->assertContains('action.process.add_online_balance.dollarpay.swappay', $keys);
        $this->assertContains('action.process.add_online_balance.swappay.reply', $keys);
        $this->assertContains('action.process.add_online_balance.swappay.reply.invoice', $keys);
    }

    public function test_missing_custom_text_key_does_not_crash(): void
    {
        $controller = new CustomTextController();
        $text = $controller->getText('action.process.add_online_balance.dollarpay.swappay');

        $this->assertNotSame('', $text);
        $this->assertNotFalse($text);
        $this->assertNotNull($text);
    }
}
