<?php

namespace App\Http\Controllers;
use App\Models\UsedGiftCard;

use Illuminate\Http\Request;

class UsedGiftCardController extends Controller
{
    public function addGiftCardToUserAccount($giftCardsId, $account_id, $code)
    {
        // $check = UsedGiftCard::where('code', $code)
        //     ->where('account_id', $account_id)
        //     ->first();

        // if ($check) {
        //     return false;
        // } else {
        $totalUsedCount = UsedGiftCard::where('gift_cards_id', $giftCardsId)
            ->count();

        $giftController = new GiftCardController();
        \Log::info("totalUsedCount :$totalUsedCount");

        // $isValid = ;
        // \Log::info("isValid :$isValid");
        if ($giftController->checkGiftCardActive($code, $totalUsedCount)) {
            $giftCard = new UsedGiftCard();
            $giftCard->gift_cards_id = $giftCardsId;
            $giftCard->account_id = $account_id;
            $giftCard->save();
            $accounBalanceCntrl = new AccountBallanceController();
            $accounBalanceCntrl->incUserAccuntBalance($account_id, $giftController->getGifcardDiscount($code));
            return true;
        }
        return false;

        // }
    }
    public function getCountOfUsePerUser($giftCardsId, $account_id)
    {
        $giftCard = UsedGiftCard::where('gift_cards_id', $giftCardsId)->where('account_id', $account_id)->count();
        return $giftCard;
    }
}
