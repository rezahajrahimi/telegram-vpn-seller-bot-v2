<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_code_id',
        'account_id',
        'product_id',
        'discount_amount',
        'applied_at',
    ];

    protected $casts = [
        'discount_amount' => 'float',
        'applied_at' => 'datetime',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
}
