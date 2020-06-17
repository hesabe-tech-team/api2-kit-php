<?php

require_once 'Libraries/HesabeCrypt.php';
require_once 'Controllers/ModelBindingController.php';

/**
 * This Class handles the payment checkout request and response.
 *
 * @author Hesabe
 */
class CheckoutController
{
    public $baseUrl;
    public $merchantCode;
    public $secretKey;
    public $ivKey;
    public $accessCode;
    public $hesabeCrypt;
    public $modelBinding;

    public function __construct($paymentApiUrl, $merchantCode, $secretKey, $ivKey, $accessCode)
    {
        $this->baseUrl = $paymentApiUrl;
        // Get all three values from Merchant Panel, Profile section
        $this->merchantCode = $merchantCode;      // Use Merchant Code
        $this->secretKey = $secretKey;            // Use Secret key
        $this->ivKey = $ivKey;                    // Use Iv Key
        $this->accessCode = $accessCode;          // Use Access Code
        $this->hesabeCrypt = new HesabeCrypt();   // instance of Hesabe Crypt library
        $this->modelBinding = new ModelBindingController();
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
                "accessCode: $this->accessCode.ll",
                "Accept: application/json"
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        //Get encrypted and decrypted data response
        return [$response , $decryptResponseData] = $this->decryptResponse($response);
    }

    /**
     * Redirect to payment gateway to complete the process
     *
     * @param string $token Encrypted data
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
     *
     * @return array De-serialize the decrypted response
     *
     */
    public function paymentResponse($responseData)
    {
        //Decrypt the response received in the data query string
        $decryptResponse = $this->hesabeCrypt::decrypt($responseData, $this->secretKey, $this->ivKey);

        //De-serialize the decrypted response
        $decryptResponseData = json_decode($decryptResponse, true);

        //Binding the decrypted response data to the entity model
        $decryptedResponse = $this->modelBinding->paymentResponseData($decryptResponseData);

        //return decrypted data
        return $decryptedResponse;
    }

    /**
     * Process the response after the form data has been requested.
     *
     * @return array De-serialize the decrypted response
     *
     */
    public function decryptResponse($response)
    {
        // Decrypt the response from the checkout API
        $decryptResponse = $this->hesabeCrypt::decrypt($response, $this->secretKey, $this->ivKey);

        if (!$decryptResponse) {
            $decryptResponse = $response;
        }

        // De-serialize the JSON string into an object
        $decryptResponseData = json_decode($decryptResponse, true);

        //Binding the decrypted response data to the entity model
        $decryptedResponse = $this->modelBinding->checkoutResponseData($decryptResponseData);

        //return encrypted and decrypted data
        return [$response , $decryptedResponse];
    }
}
