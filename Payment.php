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
     *
     * @param object $data Models\CheckoutData
     *
     * @return array [$response , $decryptResponseData] Encrypted data and decrypted data
     *
     */
    public function checkoutRequest($data)
    {
        /// Serialize the payment data into JSON string
        $requestDataJson = json_encode($data);

        // Encrypt the JSON Serialized string
        $encryptedData = $this->hesabeCrypt::encrypt($requestDataJson, $this->secretKey, $this->ivKey);

        // POST the serialized string to the checkout API and receive back the response
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

        // Decrypt the response from the checkout API
        $decryptResponse = $this->hesabeCrypt::decrypt($response, $this->secretKey, $this->ivKey);

        // De-serialize the JSON string into an object
        $decryptResponseData = json_decode($decryptResponse, true);

        if ($decryptResponseData['status'] == false && $decryptResponseData['code'] != 200) {
            return [$response , $decryptResponseData];
        }
        $responseToken = $decryptResponseData['response']['data'];

        // Redirect the user to the payment page using the token from the checkout API response
        return $this->redirectToPayment($responseToken);
    }

    /**
     * Redirect to payment gateway to complete the process
     *
     * @param string $token [<description>]
     *
     * @return null [<description>]
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
     * @return array De-serialize the decrypted response
     *
     */
    public function response()
    {
        $responseData = $_GET['data'];
        //Decrypt the response received in the data query string
        $data = $this->hesabeCrypt::decrypt($responseData, $this->secretKey, $this->ivKey);

        //De-serialize the decrypted response
        return json_decode($data, true);
    }
}
