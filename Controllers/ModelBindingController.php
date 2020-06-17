<?php

require_once 'Miscellaneous/Constants.php';
require_once 'Models/HesabeCheckoutRequestModel.php';
require_once 'Models/HesabeCheckoutResponseModel.php';
require_once 'Models/HesabePaymentResponseModel.php';

use Models\HesabeCheckoutRequestModel;
use Models\HesabeCheckoutResponseModel;
use Models\HesabePaymentResponseModel;

/**
 * This class is used to bind models
 *
 * @author Hesabe
 */
class ModelBindingController
{
    public $checkoutResponseModel;
    public $paymentResponseModel;
    public $checkoutRequestModel;

    public function __construct()
    {
        $this->checkoutRequestModel = new HesabeCheckoutRequestModel();
        $this->checkoutResponseModel = new HesabeCheckoutResponseModel();
        $this->paymentResponseModel = new HesabePaymentResponseModel();
    }

    /**
     * This function is use to bind the request data into class object
     *
     * @param array $request form post data
     *
     * @return object
     */
    public function checkoutRequestData($request)
    {
        $this->checkoutRequestModel->amount = $request['amount'];
        $this->checkoutRequestModel->currency = $request['currency'];
        $this->checkoutRequestModel->paymentType = $request['paymentType'];
        $this->checkoutRequestModel->orderReferenceNumber = $request['orderReferenceNumber'];
        $this->checkoutRequestModel->version = $request['version'];
        $this->checkoutRequestModel->variable1 = $request['variable1'];
        $this->checkoutRequestModel->variable2 = $request['variable2'];
        $this->checkoutRequestModel->variable3 = $request['variable3'];
        $this->checkoutRequestModel->variable4 = $request['variable4'];
        $this->checkoutRequestModel->variable5 = $request['variable5'];
        $this->checkoutRequestModel->merchantCode = $request['merchantCode'];
        $this->checkoutRequestModel->responseUrl = $request['responseUrl'];
        $this->checkoutRequestModel->failureUrl = $request['failureUrl'];
        return $this->checkoutRequestModel;
    }

    /**
     * Get Checkout response data.
     *
     * @param array $data Checkout response data
     *
     * @return object \Models\HesabeCheckoutResponseModel.
     */
    public function checkoutResponseData($data)
    {
        $this->checkoutResponseModel->status = $data['status'];
        $this->checkoutResponseModel->code = $data['code'];
        $this->checkoutResponseModel->message = $data['message'];
        $this->checkoutResponseModel->response['data'] = ($data['code'] == Constants::SUCCESS_CODE ||
         $data['code'] == Constants::AUTHENTICATION_FAILED_CODE) ? $data['response']['data'] : $data['data'];

        return $this->checkoutResponseModel;
    }

    /**
     * Get Payment Response response data.
     *
     * @param array $data payment response data
     *
     * @return object \Models\HesabeCheckoutResponseModel.
     */
    public function paymentResponseData($data)
    {
        $this->checkoutResponseModel->status = $data['status'];
        $this->checkoutResponseModel->code = $data['code'];
        $this->checkoutResponseModel->message = $data['message'];

        $this->paymentResponseModel->resultCode = $data['response']['resultCode'];
        $this->paymentResponseModel->amount = $data['response']['amount'];
        $this->paymentResponseModel->paymentToken = $data['response']['paymentToken'];
        $this->paymentResponseModel->paymentId = $data['response']['paymentId'];
        $this->paymentResponseModel->paidOn = $data['response']['paidOn'];
        $this->paymentResponseModel->orderReferenceNumber = $data['response']['orderReferenceNumber'];
        $this->paymentResponseModel->variable1 = $data['response']['variable1'];
        $this->paymentResponseModel->variable2 = $data['response']['variable2'];
        $this->paymentResponseModel->variable3 = $data['response']['variable3'];
        $this->paymentResponseModel->variable4 = $data['response']['variable4'];
        $this->paymentResponseModel->variable5 = $data['response']['variable5'];
        $this->paymentResponseModel->method = $data['response']['method'];
        $this->paymentResponseModel->administrativeCharge = $data['response']['administrativeCharge'];

        //Get Payment response array.
        $this->checkoutResponseModel->response = $this->paymentResponseModel->getVariables();
        return $this->checkoutResponseModel;
    }
}
