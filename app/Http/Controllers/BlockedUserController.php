<?php

namespace App\Http\Controllers;

use App\Models\BlockedUser;
use Illuminate\Http\Request;

class BlockedUserController extends Controller
{
    public function __construct()
    {
        $this->blockedUser = new BlockedUser();
    }
    public function getBlockedUserList()
    {
        return $this->blockedUser->getBlockedUserList();
    }
    public function addBlockedUser($account_id, $reason)
    {
        $this->blockedUser->addBlockedUser($account_id, $reason);
    }
    public function removeBlockedUser($account_id)
    {
        $this->blockedUser->removeBlockedUser($account_id);
    }
    public function getBlockedUser($account_id)
    {
        return $this->blockedUser->getBlockedUser($account_id);
    }
    public function isBlocked($account_id)
    {
        return $this->blockedUser->isBlocked($account_id);
    }
    public function getBlockedUserCount()
    {
        return $this->blockedUser->getBlockedUserCount();
    }
}
