<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id','service_types_id'];
    protected $fillable = ['service_types_id','category_name','price'];
    /**
     * Get the user that owns the ProductCategory
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function service_type(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_types_id');
    }

}
