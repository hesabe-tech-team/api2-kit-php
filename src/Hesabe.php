<?php

declare(strict_types=1);

namespace Hesabe;

use Hesabe\Http\Sender;
use Hesabe\Http\Transport;
use Hesabe\Resource\Checkout;
use Hesabe\Resource\Refunds;
use Hesabe\Resource\Transactions;
use Hesabe\Session\MemoryTokenStore;
use Hesabe\Session\MerchantSession;
use Hesabe\Session\TokenStore;

/**
 * The client for one merchant account.
 *
 *     $hesabe = new Hesabe([
 *         'merchantCode' => '...', 'accessCode' => '...',
 *         'secretKey' => '...', 'ivKey' => '...',
 *         'environment' => 'production',
 *     ]);
 *
 * Options left out fall back to the `HESABE_*` environment variables. The environment
 * defaults to the sandbox.
 */
final class Hesabe
{
    public const VERSION = '1.0.2';

    public readonly Checkout $checkout;
    public readonly Transactions $transactions;
    public readonly Refunds $refunds;
    public readonly Webhooks $webhooks;
    private readonly Config $config;

    /**
     * @param Config|array<string, string|int|float|null> $config
     * @param Sender|null $sender Replaces the curl sender, for tests or a host's HTTP stack.
     * @param TokenStore|null $tokenStore Where the Merchant API token survives between requests.
     */
    public function __construct(
        #[\SensitiveParameter] Config|array $config = [],
        ?Sender $sender = null,
        ?TokenStore $tokenStore = null,
    ) {
        $this->config = $config instanceof Config ? $config : Config::resolve($config);

        $transport = new Transport($this->config, $sender);
        $session = new MerchantSession($transport, $tokenStore ?? new MemoryTokenStore());

        $this->checkout = new Checkout($transport, $session);
        $this->transactions = new Transactions($transport, $session);
        $this->refunds = new Refunds($transport, $session);
        $this->webhooks = new Webhooks($this->transactions);
    }

    public function environment(): string
    {
        return $this->config->environment;
    }

    public function merchantCode(): string
    {
        return $this->config->merchantCode;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['merchantCode' => $this->merchantCode(), 'environment' => $this->environment()];
    }
}
