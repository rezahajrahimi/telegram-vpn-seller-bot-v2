<?php

namespace App\Http\Controllers;

use App\Models\AgentPermisson;
use Illuminate\Http\Request;

class AgentPermissonController extends Controller
{
    public function getUserPremission(){
        $userId = auth()->user()->id;
        return AgentPermisson::where('user_id',$userId)->first();

    }
    public function getUserPremissionByAgentID($userId){
        return AgentPermisson::where('user_id',$userId)->first();

    }

    public function createANewAgentPermisson(Request $request)
    {
        try {
            $agentPermisson = new AgentPermisson();
            $agentPermisson->user_id = $request->user_id;
            $agentPermisson->minus_ballance = $request->minus_ballance;
            $agentPermisson->create_products = $request->create_products;
            $agentPermisson->delete_products = $request->delete_products;
            $agentPermisson->save();
            return response()->json($agentPermisson, 200);

        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentPremisson(Request $request){
        try {
            $agentPermisson = AgentPermisson::where('user_id',$request->user_id)->first();
            $agentPermisson->minus_ballance = $request->minus_ballance;
            $agentPermisson->create_products = $request->create_products;
            $agentPermisson->delete_products = $request->delete_products;
            $agentPermisson->update();
            return response()->json($agentPermisson, 200);

        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentPremisson($userID){
        try {
            $agentPermisson = AgentPermisson::where('user_id',$userID)->first();
            $agentPermisson->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
}
