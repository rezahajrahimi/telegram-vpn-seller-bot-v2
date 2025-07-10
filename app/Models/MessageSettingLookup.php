<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageSettingLookup extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'value',
        'description',
        'text', // Optional example for the setting
    ];
    public function scopeGetByName($query, $name)
    {
        return $query->where('name', $name)->first();
    }
    public function scopeGetByNameAndValue($query, $name, $value)
    {
        return $query->where('name', $name)->where('value', $value)->first();
    }
    // get boolean value
    public function getBooleanValueAttribute()
    {
        return $this->value === 'true';
    }
    // get text value
    public function getTextValueAttribute()
    {
        return $this->text ?? '';
    }
}
