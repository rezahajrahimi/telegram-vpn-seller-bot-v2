<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserState extends Model
{
    protected $fillable = ['chat_id', 'state', 'data'];

    protected $casts = [
        'data' => 'array'
    ];
}