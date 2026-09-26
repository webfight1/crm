<?php

namespace Tests\Unit\Seo;

use App\Outreach\Models\OutreachLead;
use PHPUnit\Framework\TestCase;

class PreferredLeadTest extends TestCase
{
    private function lead(int $id, array $attrs = []): OutreachLead
    {
        $lead = new OutreachLead();
        $lead->forceFill(['id' => $id] + $attrs);

        return $lead;
    }

    public function test_the_active_seo_client_wins_over_a_newer_plain_lead(): void
    {
        $picked = OutreachLead::pickPreferred([
            $this->lead(642),
            $this->lead(6981, ['deal_id' => 62, 'seo_stage' => 'awaiting_answer']),
            $this->lead(7000),
        ]);

        $this->assertSame(6981, $picked->id);
    }

    public function test_the_newest_wins_among_equals(): void
    {
        $this->assertSame(9, OutreachLead::pickPreferred([$this->lead(3), $this->lead(9), $this->lead(5)])->id);
        $this->assertSame(8, OutreachLead::pickPreferred([
            $this->lead(4, ['deal_id' => 1]), $this->lead(8, ['seo_stage' => 'answered']),
        ])->id);
        $this->assertNull(OutreachLead::pickPreferred([]));
    }
}
