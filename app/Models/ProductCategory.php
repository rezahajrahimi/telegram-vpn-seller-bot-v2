<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'pannel_id'];
    protected $fillable = ['pannel_id', 'category_name', 'price', 'expire_day', 'volume', 'rechargable', 'show_subscription_link', 'show_pannel_link', 'send_config_to_user', 'is_active', 'price_in_dollar', 'inbound_id', 'ip_limit', 'sample_inbound', 'allowed_user_group_ids', 'upsell_category_id'];

    protected $casts = [
        'send_config_to_user' => 'boolean',
        'allowed_user_group_ids' => 'array',
    ];

    public function isAllowedForUserGroup(?int $userGroupId): bool
    {
        $allowed = $this->allowed_user_group_ids;
        if ($allowed === null || $allowed === []) {
            return true;
        }

        $normalized = $userGroupId ?? 0;

        return in_array($normalized, array_map('intval', $allowed), true);
    }

    public function shouldSendConfigToUser(): bool
    {
        if (! array_key_exists('send_config_to_user', $this->attributes)
            || $this->attributes['send_config_to_user'] === null) {
            return true;
        }

        return filter_var($this->attributes['send_config_to_user'], FILTER_VALIDATE_BOOLEAN);
    }

    public static function extractConfigLinks(mixed $configs): array
    {
        if ($configs === null || $configs === '') {
            return [];
        }

        if (is_string($configs)) {
            $decoded = json_decode($configs, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [$configs];
            }
            $configs = $decoded;
        }

        if (! is_array($configs)) {
            return [];
        }

        if (array_is_list($configs)) {
            return array_values(array_filter($configs, fn ($link) => is_string($link) && $link !== ''));
        }

        $links = $configs['links'] ?? [];
        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter($links, fn ($link) => is_string($link) && $link !== ''));
    }

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
