<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * The issuer or the scheme declined the payment. Retrying rarely helps.
 */
final class CardException extends HesabeException
{
}
