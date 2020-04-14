<?php

use HesabeCrypt;

class Payment
{
    private $baseUrl;
    private $merchantCode;
    private $secretKey;
    private $ivKey;
    private $accessCode;

    public function __construct()
    {
        $this->baseUrl = 'https://sandbox.hesabe.com';
        // Get all three values from Merchant Panel, Profile section
        $this->merchantCode = 'XXXXXX';      // Use Merchant Code
        $this->secretKey = 'XXXXXX';         // Use Secret key
        $this->ivKey = 'XXXXXX';             // Use Iv Key
        $this->accessCode = 'XXXXXX';        // Use Access Code
    }

    /**
     * Create request for Payment
     */
    public function checkoutRequest()
    {
        $data = [
            'merchantCode' => $this->merchantCode,
            'amount' => 5.000,
            'paymentType' => 0,
            'responseUrl' => '',
            'failureUrl' => '',
            'version' => 2.0,
            'orderReferenceNumber' => '',
            'variable1' => '',
            'variable2' => '',
            'variable3' => '',
            'variable4' => '',
            'variable5' => '',
        ];

        $requestDataJson = json_encode($data);
        $encryptedData = HesabeCrypt::encrypt($requestDataJson, $this->secretKey, $this->ivKey);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "$this->baseUrl/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => array('data' => $encryptedData),
            CURLOPT_HTTPHEADER => array(
                "accessCode: $this->accessCode",
                "Accept: application/json"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $decryptResponse = HesabeCrypt::decrypt($response, $this->secretKey, $this->ivKey);
        $jsonData = json_decode($decryptResponse, true);

        $responseToken = $jsonData['response']['data'];

        return $this->redirectToPayment($responseToken);
    }

    /**
     * Redirect to payment gateway to complete the process
     */
    public function redirectToPayment($token)
    {
        header("Location: $this->baseUrl/payment?data=$responseToken");
        exit;
    }

    /**
     * Process the response after the transaction is complete
     * To use this method, make sure your SuccessUrl or FailureUrl
     * points to this method in which you'll receive a "data" param
     * as a GET request. Then you can process it accordingly.
     *
     */
    public function response()
    {
        $responseData = $_GET['data'];
        $data = HesabeCrypt::decrypt($responseData, $this->secretKey, $this->ivKey);
        echo json_decode($data, true);
    }
}
