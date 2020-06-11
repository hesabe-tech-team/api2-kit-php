<?php

require_once 'HesabeCrypt.php';

class Payment
{
    private $baseUrl;
    private $merchantCode;
    private $secretKey;
    private $ivKey;
    private $accessCode;
    private $hesabeCrypt;

    public function __construct($paymentApiUrl, $merchantCode, $secretKey, $ivKey, $accessCode)
    {
        $this->baseUrl = $paymentApiUrl;
        // Get all three values from Merchant Panel, Profile section
        $this->merchantCode = $merchantCode;      // Use Merchant Code
        $this->secretKey = $secretKey;         // Use Secret key
        $this->ivKey = $ivKey;             // Use Iv Key
        $this->accessCode = $accessCode;        // Use Access Code
        $this->hesabeCrypt = new HesabeCrypt();
    }

    /**
     * Create request for Payment
     */
    public function checkoutRequest($data)
    {
        $requestDataJson = json_encode($data);
        $encryptedData = $this->hesabeCrypt::encrypt($requestDataJson, $this->secretKey, $this->ivKey);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$this->baseUrl/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
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
        $decryptResponse = $this->hesabeCrypt::decrypt($response, $this->secretKey, $this->ivKey);
        $decryptResponseData = json_decode($decryptResponse, true);
        if ($decryptResponseData['status'] == false && $decryptResponseData['code'] != 200) {
            return [$response , $decryptResponseData];
        }
        $responseToken = $decryptResponseData['response']['data'];
        return $this->redirectToPayment($responseToken);
    }

    /**
     * Redirect to payment gateway to complete the process
     */
    public function redirectToPayment($token)
    {
        header("Location: $this->baseUrl/payment?data=$token");
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
        $data = $this->hesabeCrypt::decrypt($responseData, $this->secretKey, $this->ivKey);
        return json_decode($data, true);
    }
}
