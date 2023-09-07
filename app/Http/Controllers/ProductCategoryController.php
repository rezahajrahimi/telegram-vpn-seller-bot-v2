<?php

namespace App\Http\Controllers;
use App\Models\ProductCategory;

use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function getAllProdctCategort($servicetypeID)
    {
        return ProductCategory::where('service_types_id', $servicetypeID)->get();
    }
    public function getProdctCategoryID($name, $servicetypeID)
    {
        $data = ProductCategory::where('service_types_id', $servicetypeID)
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
        $data->service_types_id = $request->service_types_id;
        $data->category_name = $request->category_name;
        $data->price = $request->price;

        if ($data->save()) {
            return $this->getAllProdctCategort($request->service_types_id);
        } else {
            return false;
        }
    }

    public function getProdctPrice($name, $servicetypeID)
    {
        $data = ProductCategory::where('service_types_id', $servicetypeID)
            ->where('category_name', $name)
            ->first();
        if ($data != null) {
            return $data->price;
        } else {
            return -1;
        }
    }
}
