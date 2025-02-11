<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CryptomusService
{
    protected $apiKey;
    protected $merchantId;

    public function __construct()
    {
        $this->apiKey = env('CRYPTOMUS_API_KEY');
        $this->merchantId = env('CRYPTOMUS_MERCHANT_ID');
    }

    public function createPayment($amount, $currency, $orderId)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->apiKey,
        ])->post('https://api.cryptomus.com/v1/payment', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $orderId,
        ]);

        return $response->json();
    }
}
