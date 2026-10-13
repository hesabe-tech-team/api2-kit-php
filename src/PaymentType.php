<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * Values for `paymentType` at checkout.
 *
 * Which ones work depends on the account: others answer HTTP 200 with code 424 "Invalid
 * Payment Method" (probed October 2026). `HOSTED_CHECKOUT` shows Hesabe's own page with
 * every method the account has enabled.
 */
final class PaymentType
{
    public const HOSTED_CHECKOUT = 0;
    public const KNET = 1;
    public const MPGS = 2;
    public const CYBERSOURCE = 5;
    public const AMEX = 7;
    public const MPGS_AMEX = 8;
    public const MPGS_APPLE_PAY = 9;
    public const CYBERSOURCE_APPLE_PAY = 10;
    public const KNET_DEBIT_APPLE_PAY = 11;
    public const KNET_CREDIT_APPLE_PAY = 12;
    public const KNET_APPLE_PAY_INTERNATIONAL = 13;
    public const AMEX_APPLE_PAY_INTERNATIONAL = 14;
    public const CYBERSOURCE_SUBSCRIPTION = 15;
    public const GOOGLE_PAY = 16;
    public const DEEMA = 18;
    public const MPGS_SUBSCRIPTION = 19;
    public const SAMSUNG_PAY = 20;
}
