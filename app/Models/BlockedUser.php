<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    use HasFactory;
    protected $fillable = ['account_id', 'reason'];
    protected $table = 'blocked_users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $attributes = [
        'reason' => 'unknown',
    ];
    public function getBlockedUsers()
    {
        return $this->all();
    }
    public function addBlockedUser($account_id, $reason)
    {
        $this->account_id = $account_id;
        $this->reason = $reason;
        $this->save();
    }
    public function removeBlockedUser($account_id)
    {
        $this->where('account_id', $account_id)->delete();
    }
    public function getBlockedUser($account_id)
    {
        return $this->where('account_id', $account_id)->first();
    }
    public function isBlocked($account_id)
    {
        return $this->where('account_id', $account_id)->exists();
    }
    public function getBlockedUserCount()
    {
        return $this->count();
    }
}
