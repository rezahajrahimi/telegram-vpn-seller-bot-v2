<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id','pannel_id'];
    protected $fillable = ['pannel_id','category_name','price','expire_day','volume','rechargable','show_subscription_link','show_pannel_link','is_active','price_in_dollar'];
    /**
     * Get the user that owns the ProductCategory
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
     public function pannel()
    {
        return $this->belongsTo(Pannel::class, 'pannel_id');
    }
    public function comments()
    {
        return $this->hasMany(AgentProduct::class, 'product_categories_id', 'id');
    }
    public function agent_products()
    {
        return $this->hasOne(AgentProduct::class, 'product_categories_id');
    }

    public function cron_log()
    {
        return $this->hasMany(CronLog::class, 'product_cat_id', 'id');
    }
}
