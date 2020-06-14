<?php

require_once 'Controller.php';

class IndexController extends Controller
{

    /**
     * This function handles the form request and get the response
     *
     * @return response from API.
     */
    public function formSubmit()
    {
        if (isset($_POST['submit'])) {
            //Do all the submission part all here
            $payment = new Payment($this->paymentApiUrl, $_POST['merchantCode'], $this->secretKey, $this->ivKey, $this->accessCode);
            $requestData = self::getCheckoutData($_POST);
            [$encryptedResponse , $decryptResponse] = $payment->checkoutRequest($requestData);
            echo "<p style='word-break: break-all;'> <b>Encrypted Data</b>:- ".$encryptedResponse."</p>";
            echo "<p><b>Decrypted Data</b>:- </p>";
            echo "<pre>";
            print_r($decryptResponse);
            die();
            exit;
        }

        if (isset($_GET['id']) && isset($_GET['data'])) {
            $payment = new Payment($this->paymentApiUrl, $_GET['id'], $this->secretKey, $this->ivKey, $this->accessCode);
            $response = $payment->response();
            echo "<p style='word-break: break-all;'> Encrypted Data:- ".$_GET['data']."</p>";
            echo "<p><b>Decrypted Data</b>:- </p>";
            echo "<pre>";
            print_r($response);
            exit;
        }
    }

    /**
     * This function is use to bind the request data into class object
     *
     * @param array $request form post data
     *
     * @return object
     */
    public function getCheckoutData($request)
    {
        $this->checkoutDataModel->amount = $request['amount'];
        $this->checkoutDataModel->paymentType = $request['paymentType'];
        $this->checkoutDataModel->orderReferenceNumber = $request['orderReferenceNumber'];
        $this->checkoutDataModel->version = $request['version'];
        $this->checkoutDataModel->variable1 = $request['variable1'];
        $this->checkoutDataModel->variable2 = $request['variable2'];
        $this->checkoutDataModel->variable3 = $request['variable3'];
        $this->checkoutDataModel->variable4 = $request['variable4'];
        $this->checkoutDataModel->variable5 = $request['variable5'];
        $this->checkoutDataModel->merchantCode = $request['merchantCode'];
        $this->checkoutDataModel->responseUrl = $request['responseUrl'];
        $this->checkoutDataModel->failureUrl = $request['failureUrl'];
        return $this->checkoutDataModel;
    }
}
