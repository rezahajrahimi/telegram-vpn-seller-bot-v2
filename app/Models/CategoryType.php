<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryType extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name','is_active'];
    protected $casts = [
        'is_active' => 'boolean',
    ];
    // public function getIsActiveAttribute($value)
    // {
    //     return $value ;
    // }
    public function setIsActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value ? true : false;
    }
    public function product_categories()
    {
        return $this->hasMany(ProductCategory::class, 'category_type_id');
    }
}
