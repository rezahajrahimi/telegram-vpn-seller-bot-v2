<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['bot_token', 'bot_name', 'channel_id', 'admin_name', 'panel_secret', 'panel_type', 'accunt_number', 'tether_number'];
}
