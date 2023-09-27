<?php

namespace App\Http\Controllers;
use App\Models\Pannel;

use Illuminate\Http\Request;

class PannelController extends Controller
{
    public function addNewPannel(Request $request)
    {
        try {
            $pannel = new Pannel();
            $pannel->type = $request->type;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = $request->token ?? 'Bearer ';
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->save();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function updatePannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            if ($pannel) {
                $pannel->type = $request->type;
                $pannel->username = $request->username ?? 'admin';
                $pannel->password = $request->password ?? '123456';
                $pannel->token = $request->token ?? 'Bearer ';
                $pannel->location = $request->location ?? null;
                $pannel->url_port = $request->url_port ?? null;
                $pannel->admin_url = $request->admin_url ?? null;
                $pannel->capacity = $request->capacity ?? 1333333;
                if ($pannel->update()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deletePannel($id)
    {
        try {
            $pannel = Pannel::find($id);
            if ($pannel) {
                if ($pannel->delete()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
}
