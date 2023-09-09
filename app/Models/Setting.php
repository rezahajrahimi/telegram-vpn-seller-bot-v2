<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['bot_name', 'channel_id', 'panel_secret', 'panel_type', 'welcome_message'];
}
