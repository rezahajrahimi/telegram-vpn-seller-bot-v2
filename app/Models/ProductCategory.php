<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'pannel_id'];
    protected $fillable = ['pannel_id', 'category_name', 'price', 'expire_day', 'volume', 'rechargable', 'show_subscription_link', 'show_pannel_link', 'is_active', 'price_in_dollar', 'inbound_id', 'ip_limit', 'sample_inbound'];

    public function getSampleInboundAttribute($value)
    {
        if (!$value) {
            return null;
        }
        // سعی کن JSON decode کن، اگر نتوانست خود مقدار را برگردان
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }

    public function setSampleInboundAttribute($value)
    {
        // اگر آن یک آرایه است، JSON encode کن
        $this->attributes['sample_inbound'] = is_array($value) ? json_encode($value) : $value;
    }
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
        return $this->hasMany(AgentProduct::class, 'product_categories_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'product_categories_id');
    }

    /**
     * Get product category by ID
     *
     * @param int $id
     * @return \App\Models\ProductCategory|null
     */
    public function getProdctCategorByID($id)
    {
        return self::find($id);
    }

    /**
     * Get product category by ID (properly named)
     *
     * @param int $id
     * @return \App\Models\ProductCategory|null
     */
    public function getProductCategoryByID($id)
    {
        return self::find($id);
    }

}
