<?php

namespace App\Http\Controllers;
use App\Models\UsedGiftCard;

use Illuminate\Http\Request;

class UsedGiftCardController extends Controller
{
    public function addGiftCardToUserAccount($code, $account_id)
    {
        $check = UsedGiftCard::where('code', $code)
            ->where('account_id', $account_id)
            ->first();

        if ($check) {
            return false;
        } else {
            $usedCount = UsedGiftCard::where('code', $code)
                ->where('account_id', $account_id)
                ->count();

            $giftController = new GiftCardController();
            $isValid = $giftController->checkGiftCardActive($code, $usedCount);
            if ($isValid) {
                $giftCard = new UsedGiftCard();
                $giftCard->code = $code;
                $giftCard->account_id = $account_id;
                $giftCard->save();
                $accounBalanceCntrl = new AccountBallance();
                $accounBalanceCntrl->incUserAccuntBalance($account_id, $giftController->getGifcardDiscount($code));
                return true;
            } else {
                return false;
            }
        }
    }
}
