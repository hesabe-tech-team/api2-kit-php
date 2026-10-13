<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * The request reached Hesabe and Hesabe refused it.
 *
 * A 404 from the transaction lookup lands here too. It means Hesabe holds no transaction
 * under that key: unknown, created but never paid, or declined by a card scheme (Hesabe
 * records nothing for a card decline or a failed 3-D Secure, probed October 2026).
 */
final class InvalidRequestException extends HesabeException
{
}
