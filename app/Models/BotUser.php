<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Verta;

class BotUser extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['account_id', 'username','first_name','last_name'];

    /**
     * Get all of the comments for the BotUser
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'account_id', 'account_id');
    }
    public function transaction()
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
}
