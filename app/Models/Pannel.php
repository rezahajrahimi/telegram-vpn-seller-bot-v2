<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pannel extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['type', 'username', 'password', 'token', 'location', 'url_port', 'admin_url', 'capacity'];
    /**
     * Get all of the comments for the Pannel
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function proxies()
    {
        return $this->hasMany(Proxy::class, 'pannel_id');
    }
}
