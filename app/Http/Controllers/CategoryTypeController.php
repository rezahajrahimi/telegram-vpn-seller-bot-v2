<?php

namespace App\Http\Controllers;

use App\Models\CategoryType;
use Illuminate\Http\Request;

class CategoryTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CategoryType::all();
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = new CategoryType();
        $data->name = $request->name;
        $data->is_active = $request->is_active;
        if ($data->save()) {
            return $this->index();
        } else {
            return false;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CategoryType $categoryType)
    {
        return $categoryType;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request )
    {
        $data = CategoryType::find($request->id);
        $data->name = $request->name;
        $data->is_active = $request->is_active;
        if ($data->update()) {
            return $this->index();
        } else {
            return false;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = CategoryType::find($id);
        if ($data->delete()) {
            return $this->index();
        } else {
            return false;
        }
    }

    public function getActiveCategoryType()
    {
        return CategoryType::where('is_active', true)->get();
    }
    public function getCategoryTypeByID($id)
    {
        return CategoryType::find($id);
    }
    public function getCategoryTypeByName($name)
    {
        return CategoryType::where('name', $name)->first();
    }
}
