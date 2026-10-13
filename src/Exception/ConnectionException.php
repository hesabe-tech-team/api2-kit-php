<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * The request never produced a response: DNS, TLS, timeout or a refused connection.
 */
final class ConnectionException extends HesabeException
{
}
