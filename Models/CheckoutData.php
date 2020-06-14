<?php

/**
 * This class contains all parameters needs to be defined
 * before encrypting and passing to the checkout api
 */
class CheckoutData
{
    public $amount;
    public $paymentType;
    public $orderReferenceNumber;
    public $version;
    public $variable1;
    public $variable2;
    public $variable3;
    public $variable4;
    public $variable5;
    public $merchantCode;
    public $responseUrl;
    public $failureUrl;
}
