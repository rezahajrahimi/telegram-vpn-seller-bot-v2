<?php

namespace App\Http\Controllers;
use App\Models\Support;
use App\Models\SupportCategory;

use Illuminate\Http\Request;

class SupportCategoryController extends Controller
{
    public function createNewSupportCategoryItem(Request $request)
    {
        try {
            $data = new SupportCategory();
            $data->category_name = $request->category_name;
            return $data->save();
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401, $headers);

        }
    }
    public function editSupportCategoryName(Request $request)
    {
        try {
            $data = SupportCategory::where('category_name', $request->old_name)->first();
            if ($data != null) {
                $data->category_name = $request->category_name;
                $data->update();
                return true;
            } else {
                return response()->json(false, 401, $headers);
            }
        } catch (\Throwable $th) {
            \Log::info($th);
        }
    }
    public function deleteSupportCategoryByName($name)
    {
        try {
            $data = SupportCategory::where('category_name', $name)->first();
            if ($data != null) {
                $support = Support::where('support_categories_id', $data->id)->get();
                if ($support != null) {
                    $support->each->delete();
                }
                $data->delete();
                return true;
            } else {
                return response()->json(false, 401, $headers);
            }
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401, $headers);
        }
    }
    public function getAllSuportCategory()
    {
        try {
            return SupportCategory::all();
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401, $headers);

        }
    }
}
