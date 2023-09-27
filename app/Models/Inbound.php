<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    use HasFactory;
    protected $guarded = ['id','proxy_id'];
    protected $fillable = ['name', 'data', 'is_active'];
    /**
     * Get the user that owns the Inbound
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function proxy()
    {
        return $this->belongsTo(Proxy::class, 'proxy_id');
    }

}
