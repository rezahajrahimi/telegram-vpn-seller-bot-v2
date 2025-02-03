<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomText extends Model
{
    use HasFactory;
    protected $table = 'custom_texts';
    protected $fillable = ['key', 'default_text', 'custom_text'];

    public function getText($key)
    {
        return $this->where('key', $key)->first()->custom_text ?? $this->where('key', $key)->first()->default_text;
    }
    public function setText($key, $text)
    {
        $this->where('key', $key)->update(['custom_text' => $text]);
    }

}
