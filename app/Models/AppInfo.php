<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppInfo extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'version', 'image'];
    protected $casts = [
        'version' => 'string',
        'image' => 'string',
    ];
    public function version(): string
    {
        return $this->version;
    }
    public function image(): string
    {
        return $this->image;
    }
    public function versions(): array
    {
        return explode('.', $this->version);
    }
    public function getAppInfo(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'image' => $this->image,
        ];
    }
    public function setAppInfo(array $data): void
    {
        $this->name = $data['name'] ?? $this->name;
        $this->version = $data['version'] ?? $this->version;
        $this->image = $data['image'] ?? $this->image;
        $this->save();
    }

}
