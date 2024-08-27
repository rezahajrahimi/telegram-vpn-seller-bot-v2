<?php

namespace App\Http\Controllers;
use App\Models\MainMenuItem;

use Illuminate\Http\Request;

class MainMenuItemController extends Controller
{
    public function getAllMainMenuItems()
    {
        return MainMenuItem::all();
    }
    public function getMenuNameByID($id)
    {
        return MainMenuItem::where('id',$id)->first();
    }
    public function getMenuNameByAliasName($aliasName)
    {
        $data = MainMenuItem::where('alias_name',$aliasName)->first();
        if($data != null) {
            return $data->name;
        }
        return "خیر";
    }
    public function getMenuIdByName($name)
    {
        return MainMenuItem::where('name',$name)->first();
    }
    public function getAllActivatedMainMenuItems()
    {
        return MainMenuItem::where('is_active', true)
        ->orderby('position',"asc")
        ->get();
    }
    public function deActiveMainMenuItem($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = false;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function reActiveMainMenuItem($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = true;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function changeMainMenuAliasName(Request $request)
    {
        $data = MainMenuItem::where('name', $request->oldName)->first();
        if ($data != null) {
            $data->alias_name = $request->newName;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function changeMainMenuPosition(Request $request)
    {
        $data = MainMenuItem::where('name', $request->name)->first();
        $checkAvilabel = MainMenuItem::where('position', $request->position)->first();
        if ($data != null && $checkAvilabel == null) {
            $data->position = $request->position;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
}
