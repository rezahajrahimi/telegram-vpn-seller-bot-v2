<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Http\Requests\StorePaymentTypeRequest;
use App\Http\Requests\UpdatePaymentTypeRequest;

class PaymentTypeController extends Controller
{
  public function getPaymentTypes() {
    return PaymentType::all();
  }
  public function getPaymentAddressByPaymentName($name) {
    $data= PaymentType::where("name",$name)->first();
    if($data!=null){
        return $data->payment_address;
    } else{
        return null;
    }
  }
  public function isPaymentType($name) {
    $data= PaymentType::where("name",$name)->first();
    if($data!=null){
        return true;
    } else{
        return false;
    }
  }
}
