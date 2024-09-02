<?php

namespace App\Http\Controllers;
use App\Models\MainMenuItem;

use Illuminate\Http\Request;

class MainMenuItemController extends Controller
{
    public function getAllMainMenuItems()
    {
        $data = MainMenuItem::all();
        // check if data was empty create new menu
        if (count($data) == 0) {
            $menu = new MainMenuItem();
            $menu->name = 'خرید اشتراک';
            $menu->alias_name = 'خرید اشتراک';
            $menu->is_active = true;
            $menu->position = 1;
            $menu->save();
            $menu->name = 'webapp';
            $menu->alias_name = 'استفاده در وب اپلیکیشن';
            $menu->is_active = true;
            $menu->position = 2;
            $menu->save();
            $menu->name = 'سابقه خرید';
            $menu->alias_name = 'سابقه خرید';
            $menu->is_active = true;
            $menu->position = 3;
            $menu->save();
            $menu->name = 'پشتیبانی';
            $menu->alias_name = 'پشتیبانی';
            $menu->is_active = true;
            $menu->position = 4;
            $menu->save();
            $menu->name = 'آموزش استفاده و سوالات متداول';
            $menu->alias_name = 'آموزش استفاده و سوالات متداول';
            $menu->is_active = true;
            $menu->position = 5;
            $menu->save();
            $menu->name = 'اطلاعات حساب';
            $menu->alias_name = 'اطلاعات حساب';
            $menu->is_active = true;
            $menu->position = 6;
            $menu->save();
            $menu->name = 'اکانت آزمایشی';
            $menu->alias_name = 'اکانت آزمایشی';
            $menu->is_active = true;
            $menu->position = 7;
            $menu->save();
            $menu->name = 'دانلود برنامه';
            $menu->alias_name = 'دانلود برنامه';
            $menu->is_active = true;
            $menu->position = 8;
            $menu->save();
            $menu->name = 'گیف کارد';
            $menu->alias_name = 'گیف کارد';
            $menu->is_active = true;
            $menu->position = 9;
            $menu->save();
            $data = MainMenuItem::all();
        }

        return $data;
    }
    public function getMenuNameByID($id)
    {
        return MainMenuItem::where('id', $id)->first();
    }
    public function getMenuNameByAliasName($aliasName)
    {
        $data = MainMenuItem::where('alias_name', $aliasName)->first();
        if ($data != null) {
            return $data->name;
        }
        return 'خیر';
    }
    public function getMenuAliasNameByName($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            return $data->alias_name;
        }
        return 'خیر';
    }
    public function getMenuIdByName($name)
    {
        return MainMenuItem::where('name', $name)->first();
    }
    public function getAllActivatedMainMenuItems()
    {
        return MainMenuItem::where('is_active', true)->orderby('position', 'asc')->get();
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
