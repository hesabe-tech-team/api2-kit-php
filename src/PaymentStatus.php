<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * The numeric `payment_status` Hesabe sends in merchant reports.
 */
final class PaymentStatus
{
    public const FAILED = 0;
    public const SUCCESS = 1;
    public const REFUND = 2;
    public const REFUND_FAILED = 3;
    public const AUTHORIZED = 4;
    public const MULTIVENDOR_SUCCESS = 6;
    public const SUBSCRIPTION = 8;
    public const CASH_PAYMENT = 9;
    public const SUB_MERCHANT_SUBSCRIPTION = 10;
    public const MAIN_MERCHANT_SUBSCRIPTION = 11;
}
