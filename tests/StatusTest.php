<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testTheLookupsWordMeansPaid(): void
    {
        self::assertTrue(Status::isSuccessful(['status' => 'SUCCESSFUL']));
        self::assertTrue(Status::isSuccessful(['status' => ' successful ']));
        self::assertFalse(Status::isSuccessful(['status' => 'FAILED']));
    }

    public function testTheNumericStatusWinsOverTheWord(): void
    {
        self::assertTrue(Status::isSuccessful(['payment_status' => 1]));
        self::assertTrue(Status::isSuccessful(['payment_status' => ' +1 ']));
        self::assertTrue(Status::isSuccessful(['payment_status' => 6]));
        self::assertFalse(Status::isSuccessful(['payment_status' => 0, 'status' => 'SUCCESSFUL']));
        self::assertFalse(Status::isSuccessful(['payment_status' => 2]));
    }

    public function testABooleanIsNotAStatus(): void
    {
        self::assertFalse(Status::isSuccessful(['payment_status' => true]));
    }

    public function testRedirectResultCodes(): void
    {
        self::assertTrue(Status::isSuccessful(['resultCode' => 'CAPTURED']));
        self::assertFalse(Status::isSuccessful(['resultCode' => 'NOT CAPTURED']));
    }

    public function testAnythingElseIsNotPaid(): void
    {
        self::assertFalse(Status::isSuccessful(null));
        self::assertFalse(Status::isSuccessful('SUCCESSFUL'));
        self::assertFalse(Status::isSuccessful([]));
        self::assertFalse(Status::isSuccessful(['status' => true]));
    }
}
