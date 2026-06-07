<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\BotUser;
use App\Models\Pannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// use carbon
use Carbon\Carbon;

class ProductController extends Controller
{
    public function getProductConfigAndChangeStatus($selectedProductCatID, $userID)
    {
        $data = Product::where('product_categories_id', $selectedProductCatID)->where('isActive', true)->first();
        if ($data != null) {
            $data->isActive = false;
            $data->account_id = $userID;
            $data->update();
            return $data;
        } else {
            return null;
        }
    }

    public function getProductConfigById($id, $userID)
    {
        $data = Product::where('id', $id)->where('account_id', $userID)->with('product_category')->first();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getUserProductsHistoryByAccountID($userID)
    {
        $data = Product::where('account_id', $userID)->with('product_category')->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function syncUserProductsHistoryByAccountIDwithPanels($userID)
    {
        try {
            $botUser = BotUser::find($userID);
            if (!$botUser) {
                return response()->json(false, 404);
            }
            $accountId = $botUser->account_id;
            // first get all products by account id
            $products = Product::where('account_id', $accountId)
                ->with('product_category_and_panel')
                ->get();
            // log count of products
            foreach ($products as $product) {
                // secend check product is avaliable in panel
                $pannel = Pannel::find($product->product_category_and_panel->pannel_id);
                if (!$pannel) {
                    continue;
                }

                if ($pannel->type == 'sanaei') {
                    $configs = json_decode($product->configs, true) ?? [];
                    $uuid = $configs['uuid'] ?? null;
                    if ($uuid == null) {
                        continue;
                    }
                    $sn = new SanaeiPannelController();
                    $found = $sn->findClientByUUID($pannel, $uuid);
                    if (!$found) {
                        $product->delete();
                    }
                } else {
                    $hiddifcCntrl = new HiddifyPannelController();

                    $uuid = $hiddifcCntrl->extractUUID($product->subscription_link);
                    $url = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
                    $url = "{$url}/api/v2/admin/user/$uuid";

                    $secretValue = $pannel->secret_code;
                    // $subsequentResponse = Http::get($url);
                    $subsequentResponse = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Hiddify-API-Key' => $secretValue,
                    ])->get($url);
                    \Log::info("subsequentResponse->getStatusCode: " . $subsequentResponse->getStatusCode());
                    if ($subsequentResponse->getStatusCode() != 200) {
                        $product->delete();
                    }
                }
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getUserProductsHistoryByUserIDWithPagination($userId)
    {
        try {
            $botUser = BotUser::where('id', $userId)->first();
            $accountID = $botUser->account_id;
            $data = Product::where('account_id', $accountID)
                ->with('product_category.pannel')
                ->paginate(10, ['*'], 'page');
            return $data;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getActiveProductsByProductCatID($selectedProductCatID)
    {
        $data = Product::where('product_categories_id', $selectedProductCatID)->where('isActive', true)->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function addNewProductDetails(Request $request)
    {
        $data = new Product();
        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->remark = $request->remark;
        $data->deactive_by_admin = false;
        if ($data->save()) {
            return $this->getActiveProductsByProductCatID($request->product_categories_id);
        } else {
            return false;
        }
    }
    public function addOrUpdateProductDetailsBySubscriptionLink(Request $request)
    {
        $data = Product::where('subscription_link', $request->subscription_link)->first();
        if ($data != null) {
            $data->account_id = $request->account_id;
            $data->update();

            return true;
        }

        $data = new Product();
        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->isActive = false;
        $data->account_id = $request->account_id;
        $data->remark = $request->remark;
        $data->deactive_by_admin = false;
        if ($data->save()) {
            return true;
        } else {
            return false;
        }
    }
    public function addAutomatedProductDetails(Request $request)
    {
        $data = new Product();
        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->isActive = false;
        $data->account_id = $request->account_id;
        $data->remark = $request->remark;
        $data->deactive_by_admin = false;
        if ($data->save()) {
            return $this->getActiveProductsByProductCatID($request->product_categories_id);
        } else {
            return false;
        }
    }
    public function getLastInsertedProductId()
    {
        $data = Product::orderBy('id', 'desc')->first();
        if ($data != null) {
            return $data->id;
        } else {
            return 1;
        }
    }
    public function deleteProduct($id)
    {
        try {
            $data = Product::find($id);
            if ($data != null) {
                $catId = $data->product_categories_id;
                $data->delete();

                return $this->getActiveProductsByProductCatID($catId);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function delete_product_by_uuid($uuid)
    {
        try {
            $subscriptionLink = "/{$uuid}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $data = Product::where('subscription_link', $subscriptionLink)->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
            return response()->json(false, 500);
        }
    }

    public function delete_sanaei_product_by_uuid($uuid)
    {
        try {
            // Search for Sanaei products by UUID in configs field
            $data = Product::where('configs', 'like', '%"uuid":"' . $uuid . '"%')->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
            return response()->json(false, 500);
        }
    }

    public function delete_marzban_product_by_username($username)
    {
        try {
            $data = Product::where('remark', $username)->first();
            if ($data != null) {
                $data->delete();

                return true;
            }

            return false;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return false;
        }
    }

    public function deleteProductByProductID($id)
    {
        try {
            $data = Product::where('id', $id)->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getLastBuyersByCatIdAndCount($product_categories_id, $count)
    {
        try {
            $data = Product::where('product_categories_id', $product_categories_id)->where('isActive', false)->with('user')->orderBy('id', 'desc')->take($count)->get();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }

    public function getCountOfProductSelledSummeryByCatID($product_categories_id)
    {
        try {
            $last30 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 1);
            $last90 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 3);
            $last180 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 6);
            $last365 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 12);
            return response()->json(['ماه گذشته' => $last30, 'سه ماه گذشته' => $last90, 'شش ماه گذشته' => $last180, 'یکسال گذشته' => $last365], 200);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getCountOfSellProductBYCatIdAndMonth($product_categories_id, $month)
    {
        // get count of Product in last month  by id
        $fromDate = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $tillDate = Carbon::now()->subMonth()->endOfMonth($month)->toDateString();

        $data = Product::where('product_categories_id', $product_categories_id)
            ->whereBetween('updated_at', [$fromDate, $tillDate])
            ->count();
        return $data;
    }
    public function getLastProductSelled($count)
    {
        $data = Product::with(['user', 'product_category'])
            ->orderBy('id', 'desc')
            ->take($count)
            ->get();
        return $data;
    }

}
