<?php

namespace App\Http\Controllers;

use App\Models\AgentProduct;
use Illuminate\Http\Request;

class AgentProductController extends Controller
{
    public function createANewAgentProduct(Request $request)
    {
        try {
            $agentProduct = new AgentProduct();
            $agentProduct->product_categories_id = $request->product_categories_id;
            $agentProduct->user_id = $request->user_id;
            $agentProduct->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $agentProduct->price = $request->price ?? 0.00;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.00;
            $agentProduct->save();
            return response()->json($agentProduct, 200);

        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentProduct(Request $request)
    {
        try {
            $agentProduct = AgentProduct::find($request->id);
            $agentProduct->product_categories_id = $request->product_categories_id;
            $agentProduct->user_id = $request->user_id;
            $agentProduct->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $agentProduct->price = $request->price ?? 0.00;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.00;
            $agentProduct->update();
            return response()->json($agentProduct, 200);

        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentProduct($id){
        try {
            $agentProduct = AgentProduct::find($request->id);
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function getAgentProductsByUserID($userID){
        try {
            return AgentProduct::where('user_id',$userID)->get();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }
    public function getAgentProductsByID($ID){
        try {
            return AgentProduct::first('id',$ID)->get();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }
}
