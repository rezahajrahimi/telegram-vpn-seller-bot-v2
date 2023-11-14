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
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    /**
     * Get all of the comments for the BotUser
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'account_id', 'account_id')->with('product_category');
    }
    public function transaction()
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id')->with('payment_types', 'transaction_image','user');
    }
   /**
    * Get the user associated with the BotUser
    *
    * @return \Illuminate\Database\Eloquent\Relations\HasOne
    */
   public function ballance()
   {
       return $this->hasOne(AccountBallance::class, 'account_id', 'account_id');
   }
   public function logs()
   {
       return $this->hasMany(Log::class, 'account_id', 'account_id');
   }

}
