<?php

namespace App\Http\Controllers;

use App\Services\CryptomusService;
use Illuminate\Http\Request;

class CryptomusController extends Controller
{
    protected $cryptomusService;

    public function __construct(CryptomusService $cryptomusService)
    {
        $this->cryptomusService = $cryptomusService;
    }

    public function createPayment(Request $request)
    {
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $orderId = $request->input('order_id');

        $response = $this->cryptomusService->createPayment($amount, $currency, $orderId);

        return response()->json($response);
    }

    public function createPayment(Request $request)
    {
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $orderId = $request->input('order_id');

        $response = $this->cryptomusService->createPayment($amount, $currency, $orderId);

        if (isset($response['result']['url'])) {
            // Save payment details to database
            $payment = new Payment([
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_url' => $response['result']['url'],
                'status' => 'pending',
            ]);
            $payment->save();

            return response()->json([
                'payment_url' => $response['result']['url'],
                'payment_id' => $payment->id,
            ]);
        }

        return response()->json(['error' => 'Failed to create payment'], 400);
    }
    public function handleWebhook(Request $request)
    {
        $data = $request->all();
        $signature = $request->header('Signature');
    
        // Validate the signature
        $expectedSignature = md5(base64_encode(json_encode($data)) . env('CRYPTOMUS_API_KEY'));
        if ($signature !== $expectedSignature) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
    
        // Validate the webhook data
        if (!isset($data['order_id']) || !isset($data['status'])) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }
    
        // Find the payment record
        $payment = Payment::where('order_id', $data['order_id'])->first();
    
        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }
    
        // Update payment status
        $payment->status = $data['status'];
        $payment->save();
    
        return response()->json(['message' => 'Webhook received and processed']);
    }
}