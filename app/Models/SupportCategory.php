<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['category_name'];
    /**
     * Get all of the comments for the SupportCategory
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Support(): HasMany
    {
        return $this->hasMany(Support::class, 'support_categories_id');
    }

}
