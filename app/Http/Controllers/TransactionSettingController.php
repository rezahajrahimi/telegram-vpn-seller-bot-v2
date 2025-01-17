<?php

namespace App\Http\Controllers;

use App\Models\TransactionSetting;
use Illuminate\Http\Request;

class TransactionSettingController extends Controller
{
   public function getDollorTransactionSetting()
   {
       $data = TransactionSetting::first();
       if($data != null){
           return $data->dollar_transaction;
       }else{
          $data = new TransactionSetting();
          $data->dollar_transaction = false;
          $data->save();
          return $data->dollar_transaction;
       }
   }

   public function setDollorTransactionSetting(Request $request)
   {
       $data = TransactionSetting::first();
       if($data != null){
           $data->dollar_transaction = $request->dollar_transaction;
           $data->update();
           return $data->dollar_transaction;
       }else{
          $data = new TransactionSetting();
          $data->dollar_transaction = $request->dollar_transaction;
          $data->save();
          return $data->dollar_transaction;
       }
   }

}
