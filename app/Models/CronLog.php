<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    use HasFactory;
    protected $fillable = ['cron_id','user_id','product_cat_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function cron_job()
    {
        return $this->belongsTo(CronJob::class, 'cron_id');
    }
    public function product_category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_cat_id');
    }

}
