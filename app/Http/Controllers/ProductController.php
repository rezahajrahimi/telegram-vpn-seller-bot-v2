<?php

namespace App\Http\Controllers;
use App\Models\Product;

use Illuminate\Http\Request;

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
    public function getLastInsertedProductId(){
        $data = Product::orderBy('id', 'desc')->first();
        if($data != null){
            return $data->id;
        } else {
            return 1;
        }
    }
}
