<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * Too many requests. The gateway announces 60 a minute on its `/api/` routes (probed October 2026).
 */
final class RateLimitException extends HesabeException
{
}
