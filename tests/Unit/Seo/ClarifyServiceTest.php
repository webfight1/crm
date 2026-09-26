<?php

namespace Tests\Unit\Seo;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Services\ClarifyService;
use App\Seo\Services\SeoAi;
use Tests\TestCase;

class ClarifyServiceTest extends TestCase
{
    public function test_draft_without_search_console_account_has_no_empty_paragraph(): void
    {
        $lead  = new OutreachLead(['first_name' => 'Mari', 'serp_keyword' => 'katusetööd tartu', 'website' => 'katus.ee']);
        $audit = new SeoAudit(['url' => 'https://katus.ee/teenused/katusetood', 'page_source' => 'found']);

        $html = (new ClarifyService(new SeoAi()))->draft($lead, $audit);

        $this->assertStringContainsString('Tere, Mari!', $html);
        $this->assertStringContainsString('<a href="https://katus.ee/teenused/katusetood">', $html);
        $this->assertStringNotContainsString('<p></p>', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('Search Console', $html);
    }

    public function test_search_console_request_is_added_when_account_is_set(): void
    {
        // No test DB here: seed the Playbook's per-request cache directly.
        $cache = new \ReflectionProperty(\App\Seo\Playbook::class, 'cache');
        $cache->setValue(null, [
            'clarify.gsc_email' => 'seo@webfight.ee',
            // A body saved before {{gsc_request}} existed — request must be appended.
            'clarify.body'      => "Tere{{name}}!\n\n{{page_question}}",
        ]);

        try {
            $lead = new OutreachLead(['first_name' => 'Friend', 'serp_keyword' => 'uksed', 'website' => 'uks.ee']);
            $html = (new ClarifyService(new SeoAi()))->draft($lead, null);
        } finally {
            \App\Seo\Playbook::flush();
        }

        $this->assertStringContainsString('Tere!', $html);
        $this->assertStringContainsString('seo@webfight.ee', $html);
        $this->assertStringContainsString('„Piiratud“', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_edited_draft_counts_as_sent_but_another_mail_does_not(): void
    {
        $draft = '<p>Tere, Mari!</p><p>Aitäh vastuse eest! Et analüüs oleks täpne, täpsustan kahte asja:</p>'
            . '<p>1. Fraasile „katusetööd tartu“ vastab teie kodulehel minu hinnangul see leht: https://katus.ee/teenused — kas see on õige?</p>'
            . '<p>2. Kas on veel teenuseid, mille järgi tahaksite paremini leitav olla?</p>';

        $edited = str_replace('Aitäh vastuse eest!', 'Suur aitäh kiire vastuse eest!', $draft) . '<p>Head päeva!</p>';
        $other  = '<p>Tere, Mari! Saatsin eile arve, kas see jõudis kohale? Kohtume neljapäeval kell 10.</p>';

        $this->assertTrue(ClarifyService::isSameDraft($draft, $edited));
        $this->assertFalse(ClarifyService::isSameDraft($draft, $other));
        $this->assertFalse(ClarifyService::isSameDraft(null, $edited));
    }
}
