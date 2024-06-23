<?php

namespace App\Http\Controllers;

use App\Models\AgentPermisson;
use Illuminate\Http\Request;

class AgentPermissonController extends Controller
{
    public function getUserPremission()
    {
        $userId = auth()->user()->id;
        return AgentPermisson::where('user_id', $userId)->first();
    }
    public function getUserPremissionByAgentID($userId)
    {
        return AgentPermisson::where('user_id', $userId)->first();
    }

    public function createANewAgentPermisson(Request $request)
    {
        try {
            $agentPermisson = new AgentPermisson();
            $agentPermisson->user_id = $request->user_id;
            $agentPermisson->minus_ballance = $request->minus_ballance == "false" || $request->minus_ballance == false ||$request->minus_ballance == 0 ? 0 : 1;
            $agentPermisson->create_products = $request->create_products == "false" || $request->create_products == false ||$request->create_products == 0 ? 0 : 1;
            $agentPermisson->delete_products = $request->delete_products == "false" || $request->delete_products == false ||$request->delete_products == 0 ? 0 : 1;
            $agentPermisson->save();
            return response()->json($agentPermisson, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentPremisson(Request $request)
    {
        try {
            $agentPermisson = AgentPermisson::where('user_id', $request->user_id)->first();
            if ($agentPermisson == null) {
                return $this->createANewAgentPermisson($request);
            }
            $agentPermisson->minus_ballance = $request->minus_ballance == "false"|| $request->minus_ballance == 0 ? 0 : 1;
            $agentPermisson->create_products = $request->create_products == "false" || $request->create_products == 0 ? 0 : 1;
            $agentPermisson->delete_products = $request->delete_products == "false" || $request->delete_products == 0 ? 0 : 1;



            $agentPermisson->update();
            return response()->json($agentPermisson, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentPremisson($userID)
    {
        try {
            $agentPermisson = AgentPermisson::where('user_id', $userID)->first();
            $agentPermisson->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
}
