<?php

/**
 * Live checks against Hesabe's sandbox: `php tests/smoke.php`.
 *
 * The credentials are Hesabe's published sandbox account, which is public documentation and
 * moves no money. The account is shared by everyone who uses those docs, so a Merchant API
 * login here can end someone else's token, and theirs can end ours mid-run.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Hesabe\Exception\AuthenticationException;
use Hesabe\Exception\InvalidRequestException;
use Hesabe\Hesabe;
use Hesabe\PaymentType;

const SANDBOX = [
    'merchantCode' => '842217',
    'accessCode' => 'c333729b-d060-4b74-a49d-7686a8353481',
    'secretKey' => 'PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg',
    'ivKey' => '5NVdrlPVNnjo2Jy9',
    'username' => 'test',
    'password' => 'Test@1234',
    'environment' => 'sandbox',
];

$hesabe = new Hesabe(SANDBOX);
$failed = 0;

$check = static function (string $name, callable $test) use (&$failed): void {
    try {
        $detail = $test();
        echo "  PASS  {$name}" . ($detail ? " ({$detail})" : '') . "\n";
    } catch (\Throwable $exception) {
        echo "  FAIL  {$name}\n        " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
        $failed++;
    }
};

$reference = static fn (string $tag): string => 'SDK-PHP-' . $tag . '-' . (int) (microtime(true) * 1000);

echo "\nGateway (accessCode)\n";

$check('checkout.create returns a payment URL', static function () use ($hesabe, $reference): string {
    $session = $hesabe->checkout->create(
        amount: 0.2,
        orderReferenceNumber: $reference('CHK'),
        responseUrl: 'https://example.com/ok',
        failureUrl: 'https://example.com/fail',
        paymentType: PaymentType::KNET,
    );
    assert($session['token'] !== '', 'no token');
    if (!str_starts_with($session['paymentUrl'], 'https://sandbox.hesabe.com/payment?data=')) {
        throw new RuntimeException("unexpected payment URL {$session['paymentUrl']}");
    }
    return 'token ' . substr($session['token'], 0, 12) . '...';
});

$check('a wrong accessCode raises AuthenticationException', static function () use ($reference): string {
    $wrong = new Hesabe(['accessCode' => '00000000-0000-0000-0000-000000000000'] + SANDBOX);
    try {
        $wrong->checkout->create(1, $reference('AUTH'), 'https://example.com/ok', 'https://example.com/fail');
    } catch (AuthenticationException $exception) {
        return 'code ' . $exception->getHesabeCode();
    }
    throw new RuntimeException('expected a rejection');
});

$check('an unpaid order reference answers 404', static function () use ($hesabe, $reference): string {
    try {
        $hesabe->transactions->retrieveByOrderReference($reference('ENQ'));
    } catch (InvalidRequestException $exception) {
        if ($exception->getStatusCode() === 404) {
            return $exception->getMessage();
        }
        throw $exception;
    }
    throw new RuntimeException('found a transaction that cannot exist');
});

echo "\nMerchant API (panel login, bearer token)\n";

$check('refunds.list returns a page carrying stats', static function () use ($hesabe): string {
    $page = $hesabe->refunds->list();
    if (!is_array($page['data'] ?? null) || !is_array($page['stats'] ?? null)) {
        throw new RuntimeException('unexpected shape: ' . implode(', ', array_keys($page)));
    }
    return count($page['data']) . ' refunds on this page';
});

echo $failed === 0 ? "\nAll checks passed.\n" : "\n{$failed} check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
