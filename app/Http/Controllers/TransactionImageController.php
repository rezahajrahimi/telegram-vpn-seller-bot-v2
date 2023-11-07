<?php

namespace App\Http\Controllers;
use App\Models\TransactionImage;

use Illuminate\Http\Request;

class TransactionImageController extends Controller
{
    public function getTransactionImage($trID)
    {
        try {
            $data = TransactionImage::where('transaction_id', $trID)->get();
            if ($data != null) {
                return $data;
            } else {
                return response()->json('No Image', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function addNewTransactionImage(Request $request)
    {
        try {
            $data = new TransactionImage();
            $data->transaction_id = $request->transaction_id;
            $data->img_src = $request->img_src;
            $data->account_id = $request->account_id;
            $data->save();
            return $data->id;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
}
