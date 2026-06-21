<?php

namespace App\Services;

use App\Http\Controllers\AuthController;
use Illuminate\Http\JsonResponse;

class LicenseFeatureService
{
    public const SILVER_PROMO_MAX = 5;

    public function current(): string
    {
        $auth = new AuthController();

        return strtolower((string) $auth->getPowerPsLicenseType());
    }

    public function isGold(): bool
    {
        return $this->current() === 'gold';
    }

    public function isSilverOrAbove(): bool
    {
        return in_array($this->current(), ['silver', 'gold'], true);
    }

    public function isBronzeOrBelow(): bool
    {
        return in_array($this->current(), ['false', 'trial', 'boronze', 'bronze', 'free'], true);
    }

    public function goldRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'این قابلیت فقط برای لایسنس طلایی فعال است.',
        ], 403);
    }

    public function silverRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'این قابلیت برای لایسنس نقره‌ای و طلایی فعال است.',
        ], 403);
    }

    public function maxPanels(): ?int
    {
        if ($this->isBronzeOrBelow()) {
            return 1;
        }

        if ($this->current() === 'silver') {
            return 2;
        }

        return null;
    }

    public function canAddPanel(int $currentPanelCount): bool
    {
        $max = $this->maxPanels();

        return $max === null || $currentPanelCount < $max;
    }

    public function panelLimitReachedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'به محدودیت افزودن پنل رسیده اید، برای افزودن پنل جدید با پشتیبانی تماس بگیرید و اکانت خود را ارتقا بدهید.',
        ], 403);
    }
}
