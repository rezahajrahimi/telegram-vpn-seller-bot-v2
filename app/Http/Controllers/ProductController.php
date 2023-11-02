<?php

namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
// use carbon
use Carbon\Carbon;

class ProductController extends Controller
{
    public function getProductConfigAndChangeStatus($selectedProductCatID, $userID)
    {
        $data = Product::where('product_categories_id', $selectedProductCatID)
            ->where('isActive', true)
            ->first();
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
        $data = Product::where('id', $id)
            ->where('account_id', $userID)
            ->with('product_category')
            ->first();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getUserProductsHistoryByAccountID($userID)
    {
        $data = Product::where('account_id', $userID)
            ->with('product_category')
            ->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getActiveProductsByProductCatID($selectedProductCatID)
    {
        $data = Product::where('product_categories_id', $selectedProductCatID)
            ->where('isActive', true)
            ->get();
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

        if ($data->save()) {
            return $this->getActiveProductsByProductCatID($request->product_categories_id);
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
    public function getLastBuyersByCatIdAndCount($product_categories_id,$count)
    {
        try {
            $data = Product::where('product_categories_id', $product_categories_id)
                ->where('isActive', false)
                ->with('user')
                ->orderBy('id', 'desc')
                ->take($count)
                ->get();
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
        $fromDate = Carbon::now()
            ->subMonth()
            ->startOfMonth()
            ->toDateString();
        $tillDate = Carbon::now()
            ->subMonth()
            ->endOfMonth($month)
            ->toDateString();

        $data = Product::where('product_categories_id', $product_categories_id)
            ->whereBetween('updated_at', [$fromDate, $tillDate])
            ->count();
        return $data;
    }
}
