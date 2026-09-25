<?php

namespace Tests\Feature\Outreach;

use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Services\ImapHealth;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ImapHealthTest extends TestCase
{
    private function account(): OutreachEmailAccount
    {
        $a = new OutreachEmailAccount(['email' => 'veiko@webfight.ee']);
        $a->id = 7;

        return $a;
    }

    public function test_escalates_to_error_only_after_consecutive_failures(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');
        $logger->expects($this->once())->method('error')
            ->with($this->stringContains('veiko@webfight.ee'));

        foreach (range(1, ImapHealth::FAIL_THRESHOLD) as $_) {
            ImapHealth::record($this->account(), ['[CLOSED] IMAP connection broken (server response)'], $logger, 'reply');
        }
    }

    public function test_success_resets_counter_and_benign_notices_are_ignored(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->exactly(4))->method('warning');

        $broken = ['[CLOSED] IMAP connection broken (server response)'];
        ImapHealth::record($this->account(), $broken, $logger, 'reply');
        ImapHealth::record($this->account(), $broken, $logger, 'reply');
        ImapHealth::record($this->account(), ['SECURITY PROBLEM: insecure server advertised AUTH=PLAIN'], $logger, 'reply'); // counts as success
        ImapHealth::record($this->account(), $broken, $logger, 'reply');
        ImapHealth::record($this->account(), $broken, $logger, 'reply');
    }
}
