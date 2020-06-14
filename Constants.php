<?php

// $date = new DateTime();

// $data = array(
//             "formActionUrl" => 'Checkout.php',
//             "merchantCode" => '842217',
//             "amount" => mt_rand(10, 999),
//             "responseUrl" => $getUrl.'/Checkout.php/?id=842217',
//             "orderReferenceNumber" => $date->getTimestamp(),
//             "version" => "2.0",
//             "paymentApiUrl" => "http://paymentapi.test",
//             "secretKey" => "",
//             "ivKey" => "5NVdrlPVNnjo2Jy9",
//             "accessCode" => "c333729b-d060-4b74-a49d-7686a8353481",
//       );
    // private $baseUrl;
    // private $responseUrl;
    // private $failureUrl;

    // public function __construct()
    // {
    //     $protocol =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");
    //     $host = $_SERVER['HTTP_HOST'];
    //     $this->baseUrl = $protocol.$host;

    //     // This URL are defined by you to get the response from Payment Gateway
    //     define("RESPONSE_URL", $this->baseUrl.'/Checkout.php/?id=842217');
    //     define("FAILURE_URL", $this->baseUrl.'/Checkout.php/?id=842217');
    // }

// define("API_DATA", $data);

/**
 * This class is used for defining constant.
 */
class Constants
{
    public const VERSION = "2.0";

    //Payment API URL
    public const PAYMENT_API_URL = "http://paymentapi.test";

    // Get below values from Merchant Panel, Profile section
    public const ACCESS_CODE = "c333729b-d060-4b74-a49d-7686a8353481";
    public const MERCHANT_SECRET_KEY = "PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg";
    public const MERCHANT_IV = "5NVdrlPVNnjo2Jy9";
    public const MERCHANT_CODE = "842217";

    // This URL are defined by you to get the response from Payment Gateway
    public const RESPONSE_URL = "http://api2-kit-php.test/Checkout.php/?id=842217";
    public const FAILURE_URL = "http://api2-kit-php.test/Checkout.php/?id=842217";
}
