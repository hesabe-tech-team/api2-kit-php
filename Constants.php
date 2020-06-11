<?php

$protocol =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");
$host = $_SERVER['HTTP_HOST'];
$getUrl = $protocol.$host;
$date = new DateTime();

$data = array(
            "formActionUrl" => 'Checkout.php',
            "merchantCode" => '842217',
            "amount" => mt_rand(10, 999),
            "responseUrl" => $getUrl.'/Checkout.php/?id=842217',
            "failureUrl" => $getUrl.'/Checkout.php/?id=842217',
            "orderReferenceNumber" => $date->getTimestamp(),
            "version" => "2.0",
            "paymentApiUrl" => "http://paymentapi.test",
            "secretKey" => "PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg",
            "ivKey" => "5NVdrlPVNnjo2Jy9",
            "accessCode" => "c333729b-d060-4b74-a49d-7686a8353481",
      );

define("API_DATA", $data);
