<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'payment_address'];

    /**
     * Get all of the comments for the PaymentType
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_type_id');
    }
    /**
     * Get all of the comments for the PaymentType
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transaction_image(): HasMany
    {
        return $this->hasMany(TransactionImage::class, 'payment_type_id');
    }
}
