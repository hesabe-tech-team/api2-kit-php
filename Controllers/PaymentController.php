<?php

require_once 'Miscellaneous/Constants.php';
require_once 'Controllers/CheckoutController.php';
require_once 'Controllers/ModelBindingController.php';

use Models\HesabeCheckoutRequestModel;
use Models\HesabeCheckoutResponseModel;

/**
 * This Class handles the form request to the checkout controller
 * and receive the response and displays the encrypted and decrypted data.
 *
 * @author Hesabe
 */
class PaymentController
{
    public $paymentApiUrl;
    public $secretKey;
    public $ivKey;
    public $accessCode;
    public $checkoutResponseModel;
    public $modelBinding;

    public function __construct()
    {
        $this->paymentApiUrl = Constants::PAYMENT_API_URL;
        $this->secretKey = Constants::MERCHANT_SECRET_KEY;
        $this->ivKey = Constants::MERCHANT_IV;
        $this->accessCode = Constants::ACCESS_CODE;
        $this->checkoutResponseModel = new HesabeCheckoutResponseModel();
        $this->modelBinding = new ModelBindingController();
    }

    /**
     * This function handles the form request and get the response
     *
     * @return void
     */
    public function formSubmit()
    {
        if (isset($_POST['submit'])) {
            // Initialize the Payment request encryption/decryption library using the Secret Key and IV Key from the configuration
            $checkout = new CheckoutController($this->paymentApiUrl, $_POST['merchantCode'], $this->secretKey, $this->ivKey, $this->accessCode);

            // Getting the payment data into request object
            $requestData = $this->modelBinding->checkoutRequestData($_POST);

            // POST the requested object to the checkout API and receive back the response
            [$encryptedResponse , $checkoutResponseModel] = $checkout->checkoutRequest($requestData);

            // check the response and validate it
            if ($checkoutResponseModel->status == false && $checkoutResponseModel->code != Constants::SUCCESS_CODE) {
                echo "<p style='word-break: break-all;'> <b>Encrypted Data</b>:- ".$encryptedResponse."</p>";
                echo "<p><b>Decrypted Data</b>:- </p>";
                echo "<pre>";
                print_r($checkoutResponseModel);
                exit;
            }

            // Redirect the user to the payment page using the token from the checkout API response
            return $checkout->redirectToPayment($checkoutResponseModel->response['data']);
        }

        /*
         * To use this method, make sure your SuccessUrl or FailureUrl
         * points to this method in which you'll receive a "data" param
         * as a GET request. Then you can process it accordingly.
         */
        if (isset($_GET['id']) && isset($_GET['data'])) {
            // Initialize the Payment request encryption/decryption library using the Secret Key and IV Key from the configuration
            $checkout = new CheckoutController($this->paymentApiUrl, $_GET['id'], $this->secretKey, $this->ivKey, $this->accessCode);
            
            $responseData = $_GET['data'];

            //Decrypt the response received
            $decryptedResponse = $checkout->paymentResponse($responseData);

            echo "<p style='word-break: break-all;'> Encrypted Data:- ".$responseData."</p>";
            echo "<p><b>Decrypted Data</b>:- </p>";
            echo "<pre>";
            print_r($decryptedResponse);
            exit;
        }
    }
}
