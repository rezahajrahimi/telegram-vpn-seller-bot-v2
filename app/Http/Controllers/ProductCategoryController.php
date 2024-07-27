<?php

namespace App\Http\Controllers;
use App\Models\ProductCategory;
use App\Models\Product;

use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function getAllProdctCategory()
    {
        return ProductCategory::with('pannel')->orderBy('created_at')->get();
    }
    public function getProdctCategoryNameByID($id)
    {
        return ProductCategory::where('id', $id)->first();
    }
    public function deleteProductCategoryByID($id)
    {
       // delete productCategory by id with cascade if have not any product relation

        $data = ProductCategory::where('id', $id)->first();
        if ($data != null) {
            if( $data->delete() != null){
                return $this->getAllProdctCategory();
            }else{
                return response()->json(false, 404);
            }
        }
        return response()->json(false, 404);
    }
    public function getProdctCategoryByCategoryName($categoryName)
    {
        return ProductCategory::where('category_name', $categoryName)->first();
    }
    public function getAllProdctCategoryOrderByPrice()
    {
        return ProductCategory::orderBy('price')->get();
    }
    public function getAllActiveProdctCategoryOrderByPrice()
    {
        return ProductCategory::orderBy('price')->where('is_active', true)->where('category_name', '!=', 'اکانت آزمایشی')->get();
    }
    public function getProdctPannelID($name, $pannel_id)
    {
        $data = ProductCategory::where('pannel_id', $pannel_id)->where('category_name', $name)->first();
        if ($data != null) {
            return $data->id;
        } else {
            return -1;
        }
    }
    public function getProductCatIdBYExpireDayPannelIDVolume($expire_day, $pannel_id, $volume)
    {
        $data = ProductCategory::where('expire_day', $expire_day)->where('pannel_id', $pannel_id)->where('volume', $volume)->first();
        if ($data == null) {
            // create a new category with this expire_day and pannel_id and volume
            $data = new ProductCategory();
            $data->pannel_id = $pannel_id;
            $data->expire_day = $expire_day;
            $data->volume = $volume;
            $data->category_name = 'یک دسته بندی جدید ';
            $data->price = 0;
            $data->price_in_dollar = 0;
            $data->is_active = true;
            $data->rechargable = true;
            $data->show_subscription_link = true;
            $data->show_pannel_link = true;

            $data->save();
            $id = $data->id;
        }

        return $data->id;
    }
    public function addNewProductCategory(Request $request)
    {
        $data = new ProductCategory();
        $data->pannel_id = $request->pannel_id;
        $data->category_name = $request->category_name;
        $data->price = $request->price;
        $data->expire_day = $request->expire_day;
        $data->volume = $request->volume;
        $data->rechargable = $request->rechargable;
        $data->show_subscription_link = $request->show_subscription_link;
        $data->show_pannel_link = $request->show_pannel_link;
        if ($request->price_in_dollar != null && $request->price_in_dollar >= 1) {
            $data->price_in_dollar = $request->price_in_dollar;
        } else {
            $data->price_in_dollar = 0.0;
        }
        $data->is_active = true;
        if ($data->save()) {
            return $this->getAllProdctCategory();
        } else {
            return false;
        }
    }
    public function editProductCategory(Request $request)
    {
        try {
            $data = ProductCategory::find($request->id);
            $data->pannel_id = $request->pannel_id;
            $data->category_name = $request->category_name;
            $data->price = $request->price;
            $data->expire_day = $request->expire_day;
            $data->volume = $request->volume;
            $data->rechargable = $request->rechargable;
            $data->show_subscription_link = $request->show_subscription_link;
            $data->show_pannel_link = $request->show_pannel_link;
            $data->is_active = $request->is_active;
            if ($request->price_in_dollar != null && $request->price_in_dollar >= 1) {
                $data->price_in_dollar = $request->price_in_dollar;
            } else {
                $data->price_in_dollar = 0.0;
            }

            if ($data->update()) {
                return response()->json($this->getAllProdctCategory(), 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function editProductCategoryByName(Request $request)
    {
        try {
            $data = ProductCategory::where('category_name', $request->category_name)->first();
            $data->price = $request->price;
            $data->expire_day = $request->expire_day;
            $data->volume = $request->volume;
            $data->rechargable = $request->rechargable;
            $data->show_subscription_link = $request->show_subscription_link;
            $data->show_pannel_link = $request->show_pannel_link;
            $data->is_active = $request->is_active;

            if ($data->update()) {
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getProdctPrice($name, $servicetypeID)
    {
        $data = ProductCategory::where('pannel_id', $pannel_id)->where('category_name', $name)->first();
        if ($data != null) {
            return $data->price;
        } else {
            return -1;
        }
    }
    public function reActiveProductCategory($id)
    {
        try {
            $data = ProductCategory::find($id);
            $data->is_active = true;
            if ($data->update()) {
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function deActiveProductCategory($id)
    {
        try {
            $data = ProductCategory::find($id);
            $data->is_active = false;
            if ($data->update()) {
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function mostSelledProductCategory($count)
    {
        try {
            $data = Product::leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->where('product_categories.is_active', true)->groupBy('product_categories.category_name')->select('product_categories.category_name', \DB::raw('count(*) as count'))->orderBy('count', 'desc')->take($count)->get();

            // $data = ProductCategory::where('is_active', true)
            // ->leftJoin('products', 'products.product_categories_id', '=', 'product_categories.id')
            // ->groupBy('product_categories.category_name')
            // ->select('product_categories.category_name', \DB::raw('count(*) as count'))
            // ->orderBy('count', 'desc')
            // ->take($count)->get();
            if ($data != null) {
                return $data;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info('error: ' . $th);
            return null;
        }
    }
    public function getAgentProductsNotSelectedByUserID($userID)
    {
        try {
            return ProductCategory::whereDoesntHave('agent_products', function ($query) use ($userID) {
                $query->where('agent_products.user_id', '=', $userID);
            })->get();
            // $selected =  ProductCategory::with('agent_products')
            // ->whereHas('agent_products', function ($query) use($userID) {
            //     $query->where('agent_products.user_id', '=', $userID);
            // })->get();

            // return response()->json([ 'selected'=> $selected,'not_selected'=> $not_selected], 200);
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(null, 500);
        }
    }
}
