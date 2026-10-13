<?php

declare(strict_types=1);

namespace Hesabe\Resource;

use Hesabe\Amount;
use Hesabe\PaymentType;

/**
 * Payment sessions: `POST /checkout`, the only path that creates one.
 */
final class Checkout extends Resource
{
    /**
     * Creates a payment session. Nothing is charged until the customer completes the page at
     * `paymentUrl`. Sessions are single use.
     *
     * `version` is "3.0" when a saved card, channel, embedded session, subscription or
     * customer id is involved, else "2.0"; Hesabe answers 422 without it (probed October
     * 2026). An explicit `version` wins.
     *
     * @param array{recurringFrequency?: string|int, numberOfInstallments?: string|int,
     *     recurringStartDate?: string, recurringAmount?: string|int|float}|null $subscription
     * @return array{token: string, data: string, paymentUrl: string, webviewUrl?: string}
     */
    public function create(
        string|int|float $amount,
        string $orderReferenceNumber,
        string $responseUrl,
        string $failureUrl,
        int $paymentType = PaymentType::HOSTED_CHECKOUT,
        string $currency = 'KWD',
        ?string $name = null,
        ?string $email = null,
        ?string $mobileNumber = null,
        ?string $webhookUrl = null,
        string|int|float|null $commission = null,
        ?bool $saveCard = null,
        ?int $customerId = null,
        ?string $channel = null,
        bool $embedded = false,
        bool $authorize = false,
        ?array $subscription = null,
        ?string $variable1 = null,
        ?string $variable2 = null,
        ?string $variable3 = null,
        ?string $variable4 = null,
        ?string $variable5 = null,
        ?string $version = null,
    ): array {
        $needsV3 = $saveCard || ($channel !== null && $channel !== '') || $embedded || $subscription || $customerId;

        $payload = $this->withMerchantCode([
            'amount' => Amount::format($amount),
            'currency' => $currency,
            'paymentType' => $paymentType,
            'orderReferenceNumber' => $orderReferenceNumber,
            'responseUrl' => $responseUrl,
            'failureUrl' => $failureUrl,
            'version' => $version !== null && $version !== '' ? $version : ($needsV3 ? '3.0' : '2.0'),
            'name' => $name,
            'email' => $email,
            'mobile_number' => $mobileNumber,
            'webhookUrl' => $webhookUrl,
            'variable1' => $variable1,
            'variable2' => $variable2,
            'variable3' => $variable3,
            'variable4' => $variable4,
            'variable5' => $variable5,
            'channel' => $channel,
        ]);

        if ($commission !== null) {
            $payload['commission'] = Amount::format($commission);
        }
        if ($saveCard !== null) {
            $payload['saveCard'] = $saveCard;
        }
        if ($customerId !== null) {
            $payload['customer_id'] = $customerId;
        }
        if ($embedded) {
            $payload['embeddedPayment'] = true;
        }
        if ($authorize) {
            $payload['authorize'] = '1';
        }
        if ($subscription) {
            $payload['subscription'] = '1';
            foreach (['recurringFrequency', 'numberOfInstallments', 'recurringStartDate'] as $key) {
                if (isset($subscription[$key])) {
                    $payload[$key] = (string) $subscription[$key];
                }
            }
            if (isset($subscription['recurringAmount'])) {
                $payload['recurringAmount'] = Amount::format($subscription['recurringAmount']);
            }
        }

        $envelope = $this->gatewayEnvelope('POST', 'checkout', $payload, false);
        $response = is_array($envelope['response'] ?? null) ? $envelope['response'] : [];
        $data = is_string($response['data'] ?? null) ? $response['data'] : '';

        $session = [
            'token' => is_scalar($envelope['token'] ?? null) ? (string) $envelope['token'] : '',
            'data' => $data,
            // Slashes stay literal, as Python's quote() leaves them, so the URL matches the
            // sibling SDKs character for character.
            'paymentUrl' => $this->transport->config->gatewayUrl . '/payment?data='
                . str_replace('%2F', '/', rawurlencode($data)),
        ];
        if (is_string($envelope['webviewUrl'] ?? null) && $envelope['webviewUrl'] !== '') {
            $session['webviewUrl'] = $envelope['webviewUrl'];
        }
        return $session;
    }
}
