<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class ConfigNameService
{
    public const DEFAULT_PREFIX = 'bot';

    public static function getPrefix(): string
    {
        $prefix = Setting::query()->value('config_name_prefix');

        if (! filled($prefix)) {
            return self::DEFAULT_PREFIX;
        }

        $prefix = trim((string) $prefix);

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    public static function normalizePrefix(?string $prefix): string
    {
        if (! filled($prefix)) {
            return self::DEFAULT_PREFIX;
        }

        $prefix = preg_replace('/[^a-zA-Z0-9]/', '', trim($prefix)) ?? '';

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    public static function buildHiddifyName(string $accountLabel): string
    {
        return self::getPrefix() . $accountLabel;
    }

    public static function buildSanaeiClientId(string $accountLabel, ?string $randomSuffix = null): string
    {
        $suffix = $randomSuffix ?? Str::random(4);

        return self::getPrefix() . '-' . $accountLabel . '-' . $suffix;
    }

    public static function buildMarzbanFallbackUsername(int|string $chatId, int|string $productId): string
    {
        return self::getPrefix() . "{$chatId}{$productId}";
    }

    public static function buildMarzbanTestFallbackUsername(int|string $chatId): string
    {
        return self::getPrefix() . "{$chatId}Test";
    }
}
