<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * The `status` of a refund request. Undocumented; read from sandbox and production records
 * in October 2026. A refund request waits for Hesabe to approve it before money moves.
 */
final class RefundStatus
{
    public const PENDING = 0;
    public const APPROVED = 1;
    public const CANCELLED = 3;
}
