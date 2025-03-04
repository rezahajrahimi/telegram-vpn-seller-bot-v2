<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShetabVerify extends Model
{
    use HasFactory;
    protected $table = 'shetab_verifies';
    protected $fillable = [
        'payment_type_id',
        'user_id',
        'amount',
        'tracking_code',
        'status',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
