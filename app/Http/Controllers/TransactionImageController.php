<?php

namespace App\Http\Controllers;
use App\Models\TransactionImage;
use Storage;
use File;

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
            $data->user_text = $request->user_text;
            $data->save();
            return $data->id;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function saveNewTransactionImage(Request $request)
    {
        $image_url = $request->image_url;

        // download $image_url from telegram and save on disk in transaction_images folder
        $contents = file_get_contents($image_url);
        $name = substr($image_url, strrpos($image_url, '/') + 1);

        Storage::disk('public')->put("/transaction_images/$name", $contents);

        $data = new TransactionImage();
        $data->transaction_id = $request->transaction_id;
        $data->img_src = "/transaction_images/$name";

        $data->account_id = $request->account_id;
        $data->user_text = $request->user_text;
        $data->save();

        // $uuppp = Storage::url($file);

        return $data->id;
    }
}
