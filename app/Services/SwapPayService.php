<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SwapPayService
{
    protected string $apiKey;

    protected string $application;

    protected string $baseUrl = 'https://swapwallet.app/api';

    public function __construct(?string $apiKey = null, ?string $application = null)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->application = trim((string) $application);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->application !== '';
    }

    /**
     * @return array{success: bool, result?: array, error?: string, status?: int}
     */
    public function createInvoice(
        float|string $amountUsd,
        string $returnUrl,
        ?string $externalId = null,
        string $description = 'شارژ کیف پول',
        ?string $customData = null,
        string $autoConversionToken = 'USDT',
        int $ttl = 3600
    ): array {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'SwapPay credentials are not configured.'];
        }

        $payload = [
            'amount' => [
                'number' => (string) $amountUsd,
                'unit' => 'USD',
            ],
            'autoConversionToken' => $autoConversionToken,
            'ttl' => $ttl,
            'description' => $description,
            'returnUrl' => $returnUrl,
        ];

        if ($externalId !== null && $externalId !== '') {
            $payload['externalId'] = (string) $externalId;
        }
        if ($customData !== null && $customData !== '') {
            $payload['customData'] = $customData;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Apikey ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/v1/payment/' . rawurlencode($this->application) . '/invoice', $payload);

            $body = $response->json();
            Log::info('SwapPay createInvoice response', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            if ($response->successful() && is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return [
                    'success' => true,
                    'result' => $body['result'],
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'error' => is_array($body)
                    ? ($body['message'] ?? $body['error'] ?? 'Failed to create SwapPay invoice.')
                    : 'Failed to create SwapPay invoice.',
                'status' => $response->status(),
            ];
        } catch (\Throwable $th) {
            Log::error('SwapPay createInvoice exception', ['error' => $th->getMessage()]);

            return ['success' => false, 'error' => $th->getMessage()];
        }
    }

    /**
     * @return array{success: bool, result?: array, error?: string, status?: int}
     */
    public function getInvoice(string $invoiceId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'SwapPay credentials are not configured.'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Apikey ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/v1/payment/' . rawurlencode($this->application) . '/invoice/' . rawurlencode($invoiceId));

            $body = $response->json();
            Log::info('SwapPay getInvoice response', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'body' => $body,
            ]);

            if ($response->successful() && is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return [
                    'success' => true,
                    'result' => $body['result'],
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'error' => is_array($body)
                    ? ($body['message'] ?? $body['error'] ?? 'Failed to fetch SwapPay invoice.')
                    : 'Failed to fetch SwapPay invoice.',
                'status' => $response->status(),
            ];
        } catch (\Throwable $th) {
            Log::error('SwapPay getInvoice exception', [
                'invoice_id' => $invoiceId,
                'error' => $th->getMessage(),
            ]);

            return ['success' => false, 'error' => $th->getMessage()];
        }
    }

    /**
     * Pick a payment URL from SwapPay paymentLinks.
     *
     * @param  array<int, array{type?: string, url?: string}>  $paymentLinks
     */
    public static function pickPaymentUrl(array $paymentLinks, array $preferredTypes = ['WEBSITE', 'TELEGRAM_WEBAPP', 'TELEGRAM_BOT']): ?string
    {
        $byType = [];
        foreach ($paymentLinks as $link) {
            if (! is_array($link)) {
                continue;
            }
            $type = strtoupper((string) ($link['type'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($type !== '' && $url !== '') {
                $byType[$type] = $url;
            }
        }

        foreach ($preferredTypes as $type) {
            $key = strtoupper($type);
            if (! empty($byType[$key])) {
                return $byType[$key];
            }
        }

        return $byType ? reset($byType) : null;
    }
}
