<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Support extends Model
{
    use HasFactory;
    protected $guarded = ['id','support_categories_id'];
    protected $fillable = ['support_categories_id','content','response_type'];

    /**
     * Get the user that owns the Support
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function support_categories(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_categories_id');
    }

}
