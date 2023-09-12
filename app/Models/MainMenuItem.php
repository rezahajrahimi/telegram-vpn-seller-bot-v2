<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainMenuItem extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'alias_name', 'is_active', 'position'];

}
