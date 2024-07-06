<?php

namespace App\Http\Controllers;

use App\Models\AgentProduct;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class AgentProductController extends Controller
{
    public function createBatchOfUserAgentProduct(Request $request)
    {
        try {
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            $userCntrl = new UserController();

            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            if ($userID == null) {
                return response()->json(false, 201);
            }

            $selectedProductList = json_decode($request['selectedProductList'], true);
            foreach ($selectedProductList as $value) {
                $aa = json_decode($value, true);

                $value = (array) $aa;
                $req = new Request();
                $req->product_categories_id = $value['id'];
                $req->price = $value['newPrice'];
                $req->price_in_dollar = $value['newPriceInDollar'];
                $req->user_id = $userID;
                $req->is_active = true;
                $this->createANewAgentProduct($req);
            }
            $agentPremissionCntrl = new AgentPermissonController();
            $reqPermission = new Request();
            $reqPermission->user_id = $userID;
            $reqPermission->minus_ballance = $request['minusBallance'];
            $reqPermission->create_products = $request['createProducts'];
            $reqPermission->delete_products = $request['deleteProducts'];
            $adasd = $request['minusBallance'];

            $agentPremissionCntrl->updateAgentPremisson($reqPermission);
            $userCntrl->changeUserRoleToAgent($userID);
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    public function deleteBatchOfUserAgentProduct(Request $request)
    {
        try {
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            $userCntrl = new UserController();

            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            if ($userID == null) {
                return response()->json(false, 201);
            }
            $selectedProductList = json_decode($request['selectedProductList'], true);
            foreach ($selectedProductList as $value) {
                $aa = json_decode($value, true);
                $value = (array) $aa;
                if ($value['productCategoriesId'] != null) {
                    $this->deleteAgentProductByPrCatIDAndUserID($userID, $value['productCategoriesId']);
                }
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    public function createANewAgentProduct(Request $request)
    {
        try {
            $check = AgentProduct::where('product_categories_id', $request->product_categories_id)
                ->where('user_id', $request->user_id)
                ->first();
            if ($check) {
                $request->id = $check->id;
                return $this->updateAgentProduct($request);
            }
            $agentProduct = new AgentProduct();
            $agentProduct->product_categories_id = $request->product_categories_id;
            $agentProduct->user_id = $request->user_id;
            $agentProduct->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $agentProduct->price = $request->price ?? 0.0;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.0;
            $agentProduct->save();
            return response()->json($agentProduct, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentProduct(Request $request)
    {
        try {
            $agentProduct = AgentProduct::find($request->id);
            $agentProduct->product_categories_id = $request->product_categories_id;
            $agentProduct->user_id = $request->user_id;
            $agentProduct->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $agentProduct->price = $request->price ?? 0.0;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.0;
            $agentProduct->update();
            return response()->json($agentProduct, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentProduct($id)
    {
        try {
            $agentProduct = AgentProduct::find($id);
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentProductByPrCatIDAndUserID($userID, $productCatId)
    {
        \Log::info("message $userID $productCatId");
        try {
            $agentProduct = AgentProduct::where('user_id', $userID)->where('product_categories_id', $productCatId)->first();
            if (!$agentProduct) {
                return;
            }
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function getAgentProductsByUserID($userID)
    {
        try {
            return AgentProduct::where('user_id', $userID)->with('product_categories')->get();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }

    public function getAgentProductsByID($ID)
    {
        try {
            return AgentProduct::first('id', $ID)->get();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }
    /// Agent function
    public function getProductsOfLoggedAgent()
    {
        $userId = auth('sanctum')->user()->id;
        return $this->getAgentProductsByUserID($userId);
    }
    public function buyProductByAgentWithPrID(Request $request)
    {
        $selectedPrCat = ProductCategory::find($request->id);
        $userId = auth('sanctum')->user()->account_id;

        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }
        $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;

        $accBlCtrl = new AccountBallanceController();
        if ($accBlCtrl->checkUserHasBalance($userId, $productPrice, $productPriceInDollar)) {
            $pnlCntrl = new PannelController();
            $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
            // get selected item specefic data
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $prCntrl = new ProductController();
            if ($pannel->type == 'hiddify') {
                $req = new Request();
                $req->accountId = "$userId-$request->remark";
                $req->pannelID = $selectedPrCat->pannel_id;
                $req->vol = $volume;
                $req->day = $day;
                $hiddifcCntrl = new HiddifyPannelController();

                // $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); api v2
                $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

                $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

                // $userPannelLink = $pnlCntrl->getHiddifyPannelLinkByPannelID($selectedPrCat->pannel_id);
                $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

                // $userSubscriptionLInk = "$userPannelLink/$newUUID/all.txt?name=sublink-unknown&asn=unknown&mode=new";

                $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                if ($selectedPrCat->show_pannel_link == 1) {
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:{$userPannelLink} \r\n";
                }
                $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

                $resualt = app('telegram_bot')->imageMessageByLink($image, $userId, $text);
                // save as dectivate product, So we can use it in future when user want to recharge it;
                $reqProductDetails = new Request();
                $reqProductDetails->account_id = $userId;
                $reqProductDetails->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $reqProductDetails->product_categories_id = $selectedPrCat->id;
                $reqProductDetails->panel_link = "/{$newUUID}/#{$req->accountId}";
                $reqProductDetails->configs = '';
                $reqProductDetails->remark = "$userId-$request->remark";

                $prCntrl->addAutomatedProductDetails($reqProductDetails);
                $accBlCtrl->decUserAccuntBalance($userId, $productPrice, $productPriceInDollar);
                // $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');

                return "$pannel->user_link/{$newUUID}/#{$req->accountId}";
            }
        }
        return response()->json('low ballance', 401);
    }
}
