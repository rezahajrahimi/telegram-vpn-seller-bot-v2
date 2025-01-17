<?php

namespace App\Http\Controllers;

use App\Models\WebAppMenuItem;
use App\Http\Requests\StoreWebAppMenuItemRequest;
use App\Http\Requests\UpdateWebAppMenuItemRequest;
use Illuminate\Support\Facades\Artisan;

class WebAppMenuItemController extends Controller
{
    public function seed()
    {
        Artisan::call('db:seed', [
            '--class' => 'WebAppMenuItemSeeder',
        ]);
        return $this->get_web_app_menu_items();
    }
    public function get_web_app_menu_items()
    {
        try {
            $webAppMenuItems = WebAppMenuItem::all();
            if ($webAppMenuItems->count() == 0) {
                return $this->seed();
            }
            return $webAppMenuItems;
        } catch (\Throwable $th) {
            \Log::info("get_web_app_menu_items: $th");
            return response()->json(null, 500);
        }
    }
    public function get_all_active_web_app_menu_items()
    {
        try {
            $webAppMenuItems = WebAppMenuItem::where('is_active', true)->orderby('position', 'asc')->get();
            if ($webAppMenuItems->count() == 0) {
                return $this->seed();
            }
            return $webAppMenuItems;
        } catch (\Throwable $th) {
            \Log::info("get_all_active_web_app_menu_items: $th");
            return response()->json(null, 500);
        }
    }
    public function update_web_app_menu_item_by_key(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $webAppMenuItem->is_active = $request->is_active;
            $webAppMenuItem->position = $request->position;
            $webAppMenuItem->title = $request->title;
            $webAppMenuItem->subtitle = $request->subtitle;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("update_web_app_menu_item_by_key: $th");
            return response()->json(null, 500);
        }
    }
    public function change_web_app_menu_item_status(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $webAppMenuItem->is_active = !$webAppMenuItem->is_active;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("change_web_app_menu_item_status: $th");
            return response()->json(null, 500);
        }
    }
    public function change_web_app_menu_item_position(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $checkAvilabel = MainMenuItem::where('position', $request->position)->first();
            if ($checkAvilabel != null) {
                return false;
            }
            $webAppMenuItem->position = $request->position;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("change_web_app_menu_item_position: $th");
            return response()->json(null, 500);
        }
    }
}
