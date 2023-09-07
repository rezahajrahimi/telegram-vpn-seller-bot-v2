<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = ['id','account_id','payment_type_id'];
    protected $fillable = ['account_id','username','payment_type_id','amount','confirmed','recipe_number'];

    /**
     * Get the user that owns the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_types(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }
    /**
     * Get all of the comments for the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transaction_image(): HasMany
    {
        return $this->hasMany(TransactionImage::class, 'transaction_id');
    }

}
