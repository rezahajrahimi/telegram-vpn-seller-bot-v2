<?php

namespace App\Http\Controllers;

use App\Models\AppInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;



class AppInfoController extends Controller
{
    public function index()
    {
        $appInfo = AppInfo::first();
        if (empty($appInfo)) {
            // seed appinfo
            $data = new AppInfo();
            $data->name = 'Power PS';
            $data->version = '1.0.0';
            $data->image = 'default.png'; // Set a default image or handle it as needed
            $data->save();
            $appInfo = $data; // Reassign to the newly created AppInfo instance
        }
        return response()->json($appInfo->getAppInfo());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'nullable|string|max:50',
        ]);
        // image is 

        $appInfo = AppInfo::first();
        $appInfo->setAppInfo($data);

        return response()->json(['message' => 'App info updated successfully']);
    }
    public function save_image(Request $request)
    {
        $image = $request->file('image');
        $imagePath = 'images/appinfo/';
        $imageName = time() . '.' . $image->getClientOriginalExtension(); // نام یکتا با پسوند
        try {
            // ایجاد دایرکتوری اگر وجود ندارد
            Storage::disk('public')->makeDirectory($imagePath);

            // ذخیره فایل با نام یکتا و پسوند صحیح در دیسک public
            Storage::disk('public')->putFileAs($imagePath, $image, $imageName);

            $appInfo = AppInfo::first();
            $appInfo->image = $imagePath . $imageName;
            $appInfo->save();




        } catch (\Throwable $th) {
            \Log::info("save app image:  $th");
        }

        // Save the filename to the database or perform any other necessary actions
        // This is a placeholder for the actual implementation
        return response()->json(['message' => 'Image saved successfully']);
    }
}
