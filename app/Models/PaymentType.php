<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'merchant_id','type','is_active'];

    /**
     * Get all of the comments for the PaymentType
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_type_id');
    }
    public function shetab_verifies()
    {
        return $this->hasMany(ShetabVerify::class, 'payment_type_id', 'id');
    }

}
