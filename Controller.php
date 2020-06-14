<?php

require_once 'Payment.php';
require_once 'Constants.php';
require_once 'Models/CheckoutData.php';

use Models\CheckoutData;

class Controller
{
    // public $model;
    public $paymentApiUrl;
    public $secretKey;
    public $ivKey;
    public $accessCode;
    public $checkoutDataModel;

    public function __construct()
    {
        // $this->model = new model();
        $this->paymentApiUrl = Constants::PAYMENT_API_URL;
        $this->secretKey = Constants::MERCHANT_SECRET_KEY;
        $this->ivKey = Constants::MERCHANT_IV;
        $this->accessCode = Constants::ACCESS_CODE;
        $this->checkoutDataModel = new CheckoutData();
    }
}
