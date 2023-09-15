<?php
namespace App\Lib;
namespace App\Services;
use App\Http\Controllers\PaymentTypeController;
use DB;
/*require_once 'nusoap.php';*/
use nusoap_client;
class Zarinpal
{
    public $MerchantID;
    public function __construct()
    {
        $pymCntrl = new PaymentTypeController();
        $aa = $pymCntrl->getZarinpalMerchantID();

        $this->MerchantID=$pymCntrl->getZarinpalMerchantID();
    }
    public function pay($Amount,$Email,$Mobile)
    {
                    $Description = 'فروش محصول';  // Required
                    $CallbackURL = url('/order'); // Required


                $client = new nusoap_client('https://www.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
                $client->soap_defencoding = 'UTF-8';
                $result = $client->call('PaymentRequest', [
                    [
                        'MerchantID'     => $this->MerchantID,
                        'Amount'         => $Amount,
                        'Description'    => $Description,
                        'Email'          => $Email,
                        'Mobile'         => $Mobile,
                        'CallbackURL'    => $CallbackURL,
                    ],
                ]);

                \Log::info($client);

                //Redirect to URL You can do it also by creating a form
                if ($result['Status'] == 100) {

                    return $result['Authority'];
                } else {
                    return false;
                }



    }

}
