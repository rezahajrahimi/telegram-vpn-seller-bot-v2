<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsedTestAccount extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['test_account_id','account_id'];
    /**
     * Get the user that owns the UsedTestAccount
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function testAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'test_account_id');
    }

}
