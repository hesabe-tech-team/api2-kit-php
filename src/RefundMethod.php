<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * Values for `refundMethod`. The documented sample's "0" is refused (probed October 2026).
 */
final class RefundMethod
{
    public const FULL = '1';
    public const PARTIAL = '2';
}
