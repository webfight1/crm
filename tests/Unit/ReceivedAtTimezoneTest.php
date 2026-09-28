<?php

namespace Tests\Unit;

use App\Outreach\Services\ReplyDetectionService;
use Tests\TestCase;

class ReceivedAtTimezoneTest extends TestCase
{
    public function test_utc_date_header_is_stored_in_app_timezone(): void
    {
        $svc = (new \ReflectionClass(ReplyDetectionService::class))->newInstanceWithoutConstructor();
        $parse = new \ReflectionMethod($svc, 'parseReceivedAt');

        $at = $parse->invoke($svc, "From: a@b.ee\r\nDate: Mon, 28 Sep 2026 14:07:32 +0000\r\n", null, 1);

        $this->assertSame('2026-09-28 17:07:32', $at->format('Y-m-d H:i:s'));
    }
}
