<?php

/**
 * Handels submit functionality
 *
 */
require_once 'Payment.php';
require_once 'Constants.php';

$paymentApiUrl = API_DATA['paymentApiUrl'];
$secretKey = API_DATA['secretKey'];
$ivKey = API_DATA['ivKey'];
$accessCode = API_DATA['accessCode'];

if (isset($_POST['submit'])) {
    //Do all the submission part all here
    $payment = new Payment($paymentApiUrl, $_POST['merchantCode'], $secretKey, $ivKey, $accessCode);
    unset($_POST['submit']);
    [$encryptedResponse , $decryptResponse] = $payment->checkoutRequest($_POST);
    echo "<p style='word-break: break-all;'> <b>Encrypted Data</b>:- ".$encryptedResponse."</p>";
    echo "<p><b>Decrypted Data</b>:- </p>";
    echo "<pre>";
    print_r($decryptResponse);
    die();
    exit;
}

if (isset($_GET['id']) && isset($_GET['data'])) {
    $payment = new Payment($paymentApiUrl, $_GET['id'], $secretKey, $ivKey, $accessCode);
    $response = $payment->response();
    echo "<p style='word-break: break-all;'> Encrypted Data:- ".$_GET['data']."</p>";
    echo "<p><b>Decrypted Data</b>:- </p>";
    echo "<pre>";
    print_r($response);
    exit;
}
