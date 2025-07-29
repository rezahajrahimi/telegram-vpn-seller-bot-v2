<?php

namespace App\Http\Controllers;

use App\Models\AppInfo;
use Illuminate\Http\Request;

class AppInfoController extends Controller
{
    public function index()
    {
        $appInfo = AppInfo::first();
        return response()->json($appInfo->getAppInfo());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'nullable|string|max:50',
            'image' => 'nullable|string|max:255',
        ]);

        $appInfo = AppInfo::first();
        $appInfo->setAppInfo($data);

        return response()->json(['message' => 'App info updated successfully']);
    }
}
