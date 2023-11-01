<?php

namespace App\Http\Controllers;
use App\Models\ProductCategory;

use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function getAllProdctCategory()
    {
        return ProductCategory::with('pannel')
            ->orderBy('created_at')
            ->get();
    }
    public function getProdctCategoryNameByID($id)
    {
        return ProductCategory::where('id', $id)->first();
    }
    public function getAllProdctCategoryOrderByPrice()
    {
        return ProductCategory::orderBy('price')->get();
    }
    public function getProdctPannelID($name, $pannel_id)
    {
        $data = ProductCategory::where('pannel_id', $pannel_id)
            ->where('category_name', $name)
            ->first();
        if ($data != null) {
            return $data->id;
        } else {
            return -1;
        }
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

            if ($data->update()) {
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }

    public function getProdctPrice($name, $servicetypeID)
    {
        $data = ProductCategory::where('pannel_id', $pannel_id)
            ->where('category_name', $name)
            ->first();
        if ($data != null) {
            return $data->price;
        } else {
            return -1;
        }
    }
}
