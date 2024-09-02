<?php

namespace App\Http\Controllers;

use App\Models\AgentProduct;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Pannel;
use App\Models\User;
use App\Models\AgentPermisson;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Hekmatinasser\Verta\Verta;

class AgentProductController extends Controller
{
    public function obtainBatchOfExistProductsToUser(Request $request)
    {
        $data = json_decode($request, true);

        $pannelID = $request['pannelID'];
        $accountID = $request['accountID'];
        $userID = User::where('account_id', $accountID)->first()->id;
        if ($userID == null) {
            return response()->json(false, 201);
        }
        $selectedExistConfig = json_decode($request['selectedExistConfig'], true);
        $prCatCntrl = new ProductCategoryController();
        $prCntrl = new ProductController();
        foreach ($selectedExistConfig as $value) {
            $aa = json_decode($value, true);

            $value = (array) $aa;
            $uuid = $value['uuid'];
            $req = new Request();
            $req->product_categories_id = $prCatCntrl->getProductCatIdBYExpireDayPannelIDVolume($value['packageDays'], $pannelID, $value['usageLimitGB']);
            $req->pannelID = $pannelID;
            $req->remark = $value['name'];
            $req->configs = '';
            $req->account_id = $accountID;
            $req->subscription_link = "/{$uuid}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $req->panel_link = "/{$uuid}/#{$req->remark}";

            $prCntrl->addOrUpdateProductDetailsBySubscriptionLink($req);
        }

        return response()->json(true, 200);
    }
    public function createBatchOfUserAgentProduct(Request $request)
    {
        try {
            // get count of users with agent role
            $agentsCount = User::where('role', 'agent')->count();
            $authCntrl = new AuthController();
            // check powerps license
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            $hasAccountLimitation = false;
            if ($agentsCount > 10 && $getPowerPsLicenseType == 'silver') {
                $hasAccountLimitation = true;
            }
            if ($agentsCount > 4 && $getPowerPsLicenseType == 'free') {
                $hasAccountLimitation = true;
            }
            if ($hasAccountLimitation == true) {
                // check this operation is update or create, so if user alreade have a agent role it's a update and we have to continue else it's a create

                $checkIsExist = User::where('role', 'agent')
                    ->where('account_id', $request['UserID'])
                    ->first();
                if (null == $checkIsExist) {
                    return response()->json('به محدودیت افزودن دستیار فروش رسیده اید، برای افزودن دستیار جدید با پشتیبانی تماس بگیرید و اکانت خود را ارتقا بدهید.', 201);
                }
            }
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
            $reqPermission->traffic_limitation_tb = $request['trafficLimitationTB'] ? $request['trafficLimitationTB'] : 10;
            $reqPermission->product_limitation = $request['productLimitation'] ? $request['productLimitation'] : 1000;
            $agentPremissionCntrl->updateAgentPremisson($reqPermission);
            $userCntrl->changeUserRoleToAgent($userID);
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    public function removeAgent(Request $request)
    {
        try {
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            // change agent role to user
            $userCntrl = new UserController();
            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            \Log::info("userID: $userID");
            if ($userID == null) {
                return response()->json(false, 201);
            }
            $userCntrl->changeAgentRoleToUser($userID);

            // remove agent permission

            $agentPremissionCntrl = new AgentPermissonController();
            $agentPremissionCntrl->deleteAgentPremisson($userID);

            // remove agent product
            $res = $this->deleteAllAgentProductsByUserIDAndAssignToBotAdmin($userID);

            //

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
    public function deleteAllAgentProductsByUserID($userID)
    {
        try {
            $agentProduct = AgentProduct::where('user_id', $userID)->get();
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
    public function deleteAllAgentProductsByUserIDAndAssignToBotAdmin($userID)
    {
        try {
            $agentProduct = AgentProduct::where('user_id', $userID)->get();
            if (!$agentProduct) {
                return;
            }
            $adminId = auth('sanctum')->user()->id;

            foreach ($agentProduct as $value) {
                $value->user_id = $adminId;
                $value->update();
            }

            return true;
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
    public function reChargeProductByAdminWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $selectedPrCat = ProductCategory::find($data->product_categories_id);

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;

            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = $volume;
            $req->day = $day;
            // get today date with new variable
            $today = Verta::now();
            $req->comment = "شارژ مجدد در {$today}";

            $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
            // $updateRemark = json_encode($updateRemark);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $this->addNewBotLog('product', "$data->remark توسط مدیر شارژ شد", 'charge product');

                return response()->json(true, 200);
                // dd($subsequentResponse);
            }

            return response()->json(false, 500);
        } else {
            return response()->json(false, 500);
        }
    }
    public function changeProductByAdminWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $oldPrCat = ProductCategory::find($data->product_categories_id);
        $newPrCat = ProductCategory::find($request->newPrCatID);

        if ($data != null) {
            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $day = $newPrCat->expire_day;
            $volume = $newPrCat->volume;

            $req = new Request();
            $req->pannelID = $newPrCat->pannel_id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = $volume;
            $req->day = $day;
            // get today date with new variable
            $today = Verta::now();
            if ($request->recharge == true || $request->recharge == 1) {
                $req->comment = "تغییر دسته بندی همراه با ریست زمان و حجم {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'charge product');
                }
                if ($request->changeBallance == 1 || $request->changeBallance == true) {
                    $accBalCntrl = new AccountBallanceController();
                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman < 0) {
                        $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();

                    return response()->json(true, 200);
                }
                $data->product_categories_id = $newPrCat->id;
                $data->update();

                return response()->json(true, 200);
            } else {
                $req->comment = "تغییر دسته بندی  {$today}";

                $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelOldApi($req);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'charge product');
                }
                if ($request->changeBallance == 1 || $request->changeBallance == true) {
                    $accBalCntrl = new AccountBallanceController();

                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman > 0) {
                        $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();

                    return response()->json(true, 200);
                }
            }
            $data->product_categories_id = $newPrCat->id;
            $data->update();

            return response()->json(false, 500);
        }
        return response()->json(false, 500);
    }
    public function changeActivationOfHiddifyUserByAdmin(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();

        if ($data != null) {
            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $data->product_category_and_panel->pannel_id;
            $req->uuid = $uuid;
            $today = Verta::now();

            if ($request->enable == true || $request->enable == 1 || $request->enable == 'true') {
                $req->comment = "فعال شدن بسته توسط مدیر در {$today}";
                $req->enable = true;
                $data->deactive_by_admin = false;
            } else {
                $req->comment = "غیر فعال شدن بسته توسط مدیر در {$today}";
                $req->enable = false;
                $data->deactive_by_admin = true;
            }
            // get today date with new variable

            $updateRemark = $hiddifcCntrl->changeUserActivationOfHiddifyPanelOldApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $this->addNewBotLog('product', "$data->remark توسط مدیر غیر فعال شد.", 'charge product');
                $data->update();
                return response()->json(true, 200);
            }

            return response()->json(false, 500);
        }
        return response()->json($request->id, 404);
    }
    public function getBoughtProductsPannelLinkFromServerByIdAdminMode($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $userId = auth('sanctum')->user()->account_id;

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            $hiddifcCntrl = new HiddifyPannelController();

            $panel_link = $data->panel_link;
            return $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, $panel_link);
        } else {
            return null;
        }
    }
    public function softDeleteProductByAgentWithPrIDAdminMOde($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($data != null) {
            // save current usage
            $currentStatus = $this->getBoughtProductsStatusFromServerById($id);
            if ($currentStatus == null) {
                return response()->json(null, 500);
            }
            $currentUsage = $currentStatus['current_usage_GB'];
            //

            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = 0.0;
            $req->day = 0;
            // get today date with new variable
            $today = Verta::now();
            $req->comment = "حذف شده در {$today}";

            $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanelOldApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');
                return response()->json(true, 200);
            } else {
                return response()->json(null, 500);
            }
        }
        return response()->json(false, 401);
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

        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $agentname = auth('sanctum')->user()->name;
        $remark = "$agentname -  $request->remark ";
        // check agent has terrafic limition or not
        $agentPermisson = AgentPermisson::where('user_id', $userID)->first();

        if ($agentPermisson != null) {
            $usedProductTerrafic = Product::where('account_id', $accountID)->leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->sum('product_categories.volume');

            //convert $usedProductTerrafic from Gb to TB

            if ($usedProductTerrafic != null || $usedProductTerrafic != 0) {
                $usedProductTerrafic = $usedProductTerrafic / 1000;
            }
            if ($usedProductTerrafic >= $agentPermisson->traffic_limitation_tb) {
                \Log::info("usedProductTerrafic: {$usedProductTerrafic} > {$agentPermisson->traffic_limitation_tb}");

                return response()->json('Reached to Max Terrafic Limitation', 401);
            }
            $usedProductCount = Product::where('account_id', $accountID)->count();
            if ($usedProductCount != null) {
                if ($usedProductCount >= $agentPermisson->product_limitation) {
                    \Log::info("usedProductCount: {$usedProductCount} > {$agentPermisson->product_limitation}");
                    return response()->json('Reached to Max Product Limitation', 401);
                }
            }
        }
        //
        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }

        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }
        $agentProduct = AgentProduct::where('product_categories_id', $selectedPrCat->id)
            ->where('user_id', $userID)
            ->first();
        $productPrice = $agentProduct->price;
        $productPriceInDollar = $agentProduct->price_in_dollar;

        $accBlCtrl = new AccountBallanceController();
        if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
            $pnlCntrl = new PannelController();
            $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
            // get selected item specefic data
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $prCntrl = new ProductController();
            if ($pannel->type == 'hiddify') {
                $req = new Request();
                $req->accountId = $remark;
                $req->pannelID = $selectedPrCat->pannel_id;
                $req->vol = $volume;
                $req->day = $day;
                $hiddifcCntrl = new HiddifyPannelController();

                // $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); api v2
                $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

                $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

                $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

                $reqProductDetails = new Request();
                $reqProductDetails->account_id = $accountID;
                $reqProductDetails->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $reqProductDetails->product_categories_id = $selectedPrCat->id;
                $reqProductDetails->panel_link = "/{$newUUID}/#{$req->accountId}";
                $reqProductDetails->configs = '';
                $reqProductDetails->remark = $remark;

                $prCntrl->addAutomatedProductDetails($reqProductDetails);
                $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');

                return $userPannelLink;
            }
        }
        return response()->json('low ballance', 401);
    }
    public function buyProductByUserWithPrID(Request $request)
    {
        $selectedPrCat = ProductCategory::find($request->id);

        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $agentname = auth('sanctum')->user()->name;
        $remark = "$agentname -  $request->remark ";

        //
        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }

        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }
        $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;

        $accBlCtrl = new AccountBallanceController();
        if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
            $pnlCntrl = new PannelController();
            $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
            // get selected item specefic data
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $prCntrl = new ProductController();
            if ($pannel->type == 'hiddify') {
                $req = new Request();
                $req->accountId = $remark;
                $req->pannelID = $selectedPrCat->pannel_id;
                $req->vol = $volume;
                $req->day = $day;
                $hiddifcCntrl = new HiddifyPannelController();

                // $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); api v2
                $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

                $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

                $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

                $reqProductDetails = new Request();
                $reqProductDetails->account_id = $accountID;
                $reqProductDetails->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $reqProductDetails->product_categories_id = $selectedPrCat->id;
                $reqProductDetails->panel_link = "/{$newUUID}/#{$req->accountId}";
                $reqProductDetails->configs = '';
                $reqProductDetails->remark = $remark;

                $prCntrl->addAutomatedProductDetails($reqProductDetails);
                $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');

                return $userPannelLink;
            }
        }
        return response()->json('low ballance', 401);
    }
    public function reChargeProductByUserWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $selectedPrCat = ProductCategory::find($data->product_categories_id);
        // check agent has terrafic limition or not


        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();
            // return $agentProduct;
            $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                $hiddifcCntrl = new HiddifyPannelController();

                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $selectedPrCat->expire_day;
                $volume = $selectedPrCat->volume;

                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                $req->comment = "شارژ مجدد در {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                // $updateRemark = json_encode($updateRemark);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                    $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    return response()->json(true, 200);
                    // dd($subsequentResponse);
                }
            }
            return response()->json(false, 401);
        }
        return response()->json(false, 500);
    }
    public function buyProductByAdmin(Request $request)
    {
        $selectedPrCat = ProductCategory::find($request->id);

        $accountID = $request->account_id;
        $userID = $request->user_id;
        $agentname = $request->username;
        $remark = "$agentname -  $request->remark ";

        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }

        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }
        $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;

        $accBlCtrl = new AccountBallanceController();

        $agentProduct = AgentProduct::where('product_categories_id', $selectedPrCat->id)
            ->where('user_id', $userID)
            ->first();

        if ($agentProduct != null) {
            $productPrice = $agentProduct->price;
            $productPriceInDollar = $agentProduct->price_in_dollar;
        }

        $accBlCtrl = new AccountBallanceController();
        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
        // get selected item specefic data
        $day = $selectedPrCat->expire_day;
        $volume = $selectedPrCat->volume;
        $prCntrl = new ProductController();
        if ($pannel->type == 'hiddify') {
            $req = new Request();
            $req->accountId = $remark;
            $req->pannelID = $selectedPrCat->pannel_id;
            $req->vol = $volume;
            $req->day = $day;
            $hiddifcCntrl = new HiddifyPannelController();

            // $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); api v2
            $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

            $userPannelLink = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

            $userSubscriptionLInk = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new");

            $reqProductDetails = new Request();
            $reqProductDetails->account_id = $accountID;
            $reqProductDetails->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $reqProductDetails->product_categories_id = $selectedPrCat->id;
            $reqProductDetails->panel_link = "/{$newUUID}/#{$req->accountId}";
            $reqProductDetails->configs = '';
            $reqProductDetails->remark = $remark;

            $prCntrl->addAutomatedProductDetails($reqProductDetails);
            $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته بصورت دستی کم شد.", 'minus ballance');

            return $userPannelLink;
        }
    }
    public function getAgentSelledProducts($count = 10)
    {
        $userId = auth('sanctum')->user()->account_id;
        $product = Product::where('account_id', $userId)->with('product_category_and_panel')->take($count)->orderBy('id', 'desc')->get();

        return $product;
    }
    public function getAgentSelledProductsByPagination()
    {
        try {
            $userId = auth('sanctum')->user()->account_id;
            $product = Product::where('account_id', $userId)
                ->with('product_category_and_panel')
                ->orderBy('id', 'desc')
                ->paginate(10, ['*'], 'page');

            return $product;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getBoughtProductsStatusFromServerById($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $url = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
            $url = "{$url}/api/v1/user/?uuid={$uuid}";

            $subsequentResponse = Http::get($url);

            if ($subsequentResponse->getStatusCode() == 200) {
                $checkIsHtmlPage = strpos($subsequentResponse->getBody(), '<html>');
                if ($checkIsHtmlPage !== false) {
                    return response()->json(false, 401);
                }
                // dd($subsequentResponse);
                return json_decode($subsequentResponse->getBody(), true);
            }
            return response()->json(null, 401);
        } else {
            return null;
        }
    }
    public function getBoughtProductsPannelLinkFromServerById($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $userId = auth('sanctum')->user()->account_id;

        if ($userId != $data->account_id || $data == null) {
            return response()->json(false, 401);
        }
        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            $hiddifcCntrl = new HiddifyPannelController();

            $panel_link = $data->panel_link;
            return $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->user_link, $panel_link);
        } else {
            return null;
        }
    }
    public function renameHiddifyRemark(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $userId = auth('sanctum')->user()->account_id;

        if ($userId != $data->account_id) {
            return response()->json(false, 401);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $request->name;
            $req->uuid = $uuid;

            $updateRemark = $hiddifcCntrl->updateUserNameOfHiddifyPanelOldApi($req);
            // $updateRemark = json_encode($updateRemark);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $data->remark = $request->name;
                $data->update();
                return response()->json(true, 200);
                // dd($subsequentResponse);
            }

            return response()->json(false, 401);
        } else {
            return response()->json(false, 500);
        }
    }
    public function reChargeProductByAgentWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $selectedPrCat = ProductCategory::find($data->product_categories_id);
        // check agent has terrafic limition or not

        $agentPermisson = AgentPermisson::where('user_id', $userID)->first();

        if ($agentPermisson != null) {
            $usedProductTerrafic = Product::where('account_id', $accountID)->leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->sum('product_categories.volume');
            //convert $usedProductTerrafic from Gb to TB
            if ($usedProductTerrafic != null || $usedProductTerrafic != 0) {
                $usedProductTerrafic = $usedProductTerrafic / 1000;
            }

            if ($usedProductTerrafic >= $agentPermisson->traffic_limitation_tb) {
                \Log::info("usedProductTerrafic: {$usedProductTerrafic} > {$agentPermisson->traffic_limitation_tb}");

                return response()->json('Reached to Max Terrafic Limitation', 401);
            }
        }
        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();
            // return $agentProduct;
            $productPrice = $agentProduct->price;
            $productPriceInDollar = $agentProduct->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                $hiddifcCntrl = new HiddifyPannelController();

                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $selectedPrCat->expire_day;
                $volume = $selectedPrCat->volume;

                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                $req->comment = "شارژ مجدد در {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                // $updateRemark = json_encode($updateRemark);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                    $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    return response()->json(true, 200);
                    // dd($subsequentResponse);
                }
            }
            return response()->json(false, 401);
        }
        return response()->json(false, 500);
    }
    public function changeProductByAgentWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();

        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $oldPrCat = ProductCategory::find($data->product_categories_id);
        $newPrCat = ProductCategory::find($request->newPrCatID);

        // check agent has terrafic limition or not

        $agentPermisson = AgentPermisson::where('user_id', $userID)->first();

        if ($agentPermisson != null) {
            $usedProductTerrafic = Product::where('account_id', $accountID)->leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->sum('product_categories.volume');
            //convert $usedProductTerrafic from Gb to TB
            if ($usedProductTerrafic != null || $usedProductTerrafic != 0) {
                $usedProductTerrafic = $usedProductTerrafic / 1000;
            }

            if ($usedProductTerrafic >= $agentPermisson->traffic_limitation_tb) {
                \Log::info("usedProductTerrafic: {$usedProductTerrafic} > {$agentPermisson->traffic_limitation_tb}");

                return response()->json('Reached to Max Terrafic Limitation', 401);
            }
        }
        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($oldPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();

            // $newAgentProduct = AgentProduct::find($request->newPrCatID);
            $newAgentProduct = AgentProduct::where('product_categories_id', $request->newPrCatID)
                ->where('user_id', $userID)
                ->first();

            // return $agentProduct;
            $oldProductPrice = $agentProduct->price;
            $oldProductPriceInDollar = $agentProduct->price_in_dollar;
            $productPrice = $newAgentProduct->price;
            $productPriceInDollar = $newAgentProduct->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $newPrCat->expire_day;
                $volume = $newPrCat->volume;

                $req = new Request();
                $req->pannelID = $newPrCat->pannel_id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                if ($request->recharge == true || $request->recharge == 1) {
                    $req->comment = "تغییر دسته بندی همراه با ریست زمان و حجم {$today}";

                    $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                    if ($updateRemark['status'] == 200) {
                        if ($updateRemark['msg'] !== 'ok') {
                            return response()->json(false, 401);
                        }
                        $this->addNewBotLog('product', "$data->remark توسط کاربر تغییر یافت.", 'charge product');
                    }
                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman > 0) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();
                    return response()->json(true, 200);
                }

                $req->comment = "تغییر دسته بندی  {$today}";

                $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelOldApi($req);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $this->addNewBotLog('product', "$data->remark توسط کاربر تغییر یافت.", 'charge product');
                }

                // get difference between old and new price
                $diffInToman = $newPrCat->price - $oldPrCat->price;
                $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                if ($diffInToman < 0) {
                    $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                }
                $data->product_categories_id = $newPrCat->id;
                $data->update();

                return response()->json(true, 200);
            }

            return response()->json('Low Ballance', 401);
        }

        return response()->json(false, 500);
    }
    public function changeActivationOfHiddifyUserByAgent(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        if ($accountID != $data->account_id) {
            return response()->json('This product is not yours', 401);
        }
        if ($data->deactive_by_admin == true) {
            return response()->json('This product is deactivated by admin', 401);
        }
        if ($data != null) {
            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $data->product_category_and_panel->pannel_id;
            $req->uuid = $uuid;
            $today = Verta::now();

            if ($request->enable == true || $request->enable == 1 || $request->enable == 'true') {
                $req->comment = "فعال شدن بسته توسط کاربر در {$today}";
                $req->enable = true;
            } else {
                $req->comment = "غیر فعال شدن بسته توسط کاربر در {$today}";
                $req->enable = false;
            }
            // get today date with new variable

            $updateRemark = $hiddifcCntrl->changeUserActivationOfHiddifyPanelOldApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $this->addNewBotLog('product', "$data->remark توسط کاربر غیر فعال شد.", 'charge product');
                return response()->json(true, 200);
            }

            return response()->json(false, 500);
        }
        return response()->json($request->id, 404);
    }
    public function softDeleteProductByAgentWithPrID($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }

        if ($data != null) {
            // save current usage
            $currentStatus = $this->getBoughtProductsStatusFromServerById($id);
            if ($currentStatus == null) {
                return response()->json(null, 500);
            }
            $currentUsage = $currentStatus['current_usage_GB'];
            //

            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = 0.0;
            $req->day = 0;
            // get today date with new variable
            $today = Verta::now();
            $req->comment = "حذف شده در {$today}";

            $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanelOldApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');

                $agentPremissionCntrl = new AgentPermissonController();
                $agentPr = $agentPremissionCntrl->getUserPremission();
                if ($agentPr->delete_products == 1 || $agentPr->delete_products == true) {
                    if ($currentUsage < 0.5) {
                        $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                            ->where('user_id', $userID)
                            ->first();
                        $productPrice = $agentProduct->price;
                        $accBlCtrl = new AccountBallanceController();

                        $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice, 0);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته کم حجم اضافه شد.", 'add ballance');

                        if ($inc == false) {
                            return response()->json(null, 500);
                        }
                    }
                }

                return response()->json(true, 200);
            } else {
                return response()->json(null, 500);
            }
        }
        return response()->json(false, 401);
    }
    public function softDeleteProductByUserWithPrID($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }

        if ($data != null) {
            // save current usage
            $currentStatus = $this->getBoughtProductsStatusFromServerById($id);
            if ($currentStatus == null) {
                return response()->json(null, 500);
            }
            $currentUsage = $currentStatus['current_usage_GB'];
            //

            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = 0.0;
            $req->day = 0;
            // get today date with new variable
            $today = Verta::now();
            $req->comment = "حذف شده در {$today}";

            $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanelOldApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');



                return response()->json(true, 200);
            } else {
                return response()->json(null, 500);
            }
        }
        return response()->json(false, 401);
    }
    public function addNewBotLog($type, $message, $event)
    {
        $accountID = auth('sanctum')->user()->account_id;
        $name = auth('sanctum')->user()->name;

        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $accountID, $name, $event);
        return true;
    }
}
