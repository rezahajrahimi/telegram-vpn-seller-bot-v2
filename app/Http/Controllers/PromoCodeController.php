<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function index()
    {
        return response()->json(PromoCode::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'type' => 'required|in:percent,fixed_toman,fixed_dollar',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'min_order_amount' => 'nullable|numeric|min:0',
            'allowed_category_ids' => 'nullable|array',
            'allowed_user_group_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $promo = PromoCode::create($data);

        return response()->json($promo, 201);
    }

    public function update(Request $request, int $id)
    {
        $promo = PromoCode::findOrFail($id);
        $data = $request->validate([
            'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $id,
            'type' => 'sometimes|in:percent,fixed_toman,fixed_dollar',
            'value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'min_order_amount' => 'nullable|numeric|min:0',
            'allowed_category_ids' => 'nullable|array',
            'allowed_user_group_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $promo->update($data);

        return response()->json($promo);
    }

    public function destroy(int $id)
    {
        PromoCode::findOrFail($id)->delete();

        return response()->json(true);
    }

    public function usages(int $id)
    {
        $promo = PromoCode::with('usages')->findOrFail($id);

        return response()->json($promo->usages);
    }

    public function validateCode(Request $request, PromoCodeService $service)
    {
        $request->validate([
            'code' => 'required|string',
            'account_id' => 'required|string',
            'category_id' => 'required|integer',
            'price_toman' => 'required|numeric',
            'price_dollar' => 'nullable|numeric',
        ]);

        $result = $service->validate(
            $request->code,
            $request->account_id,
            (int) $request->category_id,
            (float) $request->price_toman,
            (float) ($request->price_dollar ?? 0)
        );

        return response()->json($result, $result['valid'] ? 200 : 422);
    }
}
