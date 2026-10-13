<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * A payload failed to decrypt, or a redirect or webhook did not match what Hesabe reports.
 */
final class SignatureException extends HesabeException
{
}
