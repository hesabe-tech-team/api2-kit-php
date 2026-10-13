<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * Hesabe rejected the access code, the merchant code, the panel login or the bearer token.
 */
final class AuthenticationException extends HesabeException
{
}
