<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentPermisson extends Model
{
    use HasFactory;
    protected $fillable = [ 'user_id', 'minus_ballance','create_products', 'delete_products'];
    public function user()
    {
        return $this->belongsTo(Pannel::class, 'user_id');

    }

}
