<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionImage extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'transaction_id', 'payment_type_id'];

    protected $fillable = ['account_id', 'username', 'transaction_id', 'payment_type_id', 'img_src'];

    /**
     * Get the user that owns the TransactionImage
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
    public function payment_type(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }
}
