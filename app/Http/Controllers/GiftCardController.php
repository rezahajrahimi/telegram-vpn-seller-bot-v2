<?php

namespace App\Http\Controllers;
use App\Models\GiftCard;
use Verta;
use Carbon\Carbon;

use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function createNewGiftCard(Request $request)
    {
        $giftCard = GiftCard::where('code', $request->code)->first();
        if ($giftCard) {
            return response()->json('duplicate', 401);
        } else {
            $giftCard = new GiftCard();
            $giftCard->code = $request->code;
            $giftCard->start_date = $request->start_date;
            $giftCard->end_date = $request->end_date;
            $giftCard->discount = $request->discount;
            $giftCard->count_of_use = $request->count_of_use;
            $giftCard->count_of_use_per_user = $request->count_of_use_per_user;
            $giftCard->save();
            return $giftCard;
        }
    }
    public function getGiftCardList()
    {
        return GiftCard::all();
    }
    public function updateGiftCard(Request $request)
    {

        $giftCard = GiftCard::where('code', $request->code)->first();
        if ($giftCard) {
            $giftCard->code = $request->code;
            $giftCard->start_date = $request->start_date;
            $giftCard->end_date = $request->end_date;
            $giftCard->discount = $request->discount;
            $giftCard->count_of_use = $request->count_of_use;
            $giftCard->count_of_use_per_user = $request->count_of_use_per_user;
            $giftCard->update();
            return true;
        } else {
            return false;
        }
    }
    public function checkGiftCardActive($code, $usedCount)
    {
        $giftCard = GiftCard::where('code', $code)->first();
        if ($giftCard) {
            $today = date('Y-m-d H:i:s');
            if ($giftCard->start_date <= $today && $giftCard->end_date >= $today) {
                if ($giftCard->count_of_use >= $usedCount) {
                    return true;
                }
            } else {
                return false;
            }
        }
    }
    public function getGifcardDiscount($code)
    {
        $giftCard = GiftCard::where('code', $code)->first();
        if ($giftCard) {
            return $giftCard->discount;
        } else {
            return 0;
        }
    }
    public function deleteGiftCardByCode($code)
    {
        $giftCard = GiftCard::where('code', $code)->first();
        if ($giftCard) {
            $giftCard->delete();
            return true;
        } else {
            return false;
        }
    }

    public function getMiladyDate($oldDate)
    {
        \Log::info("aaaaaaaaaaaaaaaa $oldDate");

        try {
            if ($oldDate != null) {
                $v = explode('/', $oldDate);
                $y = $v[0];
                $m = $v[1];
                $d = $v[2];

                $newDat = Verta::jalaliToGregorian($y, $m, $d);
                $car = new Carbon();
                $car->year = $newDat[0];
                $car->month = $newDat[1];
                $car->day = $newDat[2];
                return $car;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            if ($oldDate != null) {
                $v = explode('-', $oldDate);
                $y = $v[0];
                $m = $v[1];
                $d = $v[2];

                $newDat = Verta::jalaliToGregorian($y, $m, $d);
                $car = new Carbon();
                $car->year = $newDat[0];
                $car->month = $newDat[1];
                $car->day = $newDat[2];
                return $car;

            } else {
                return null;
            }
        }
    }
}
