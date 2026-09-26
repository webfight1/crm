<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\WarmClientService;
use PHPUnit\Framework\TestCase;

class WarmClientSameCompanyTest extends TestCase
{
    public function test_legal_form_case_and_punctuation_are_ignored(): void
    {
        $this->assertTrue(WarmClientService::sameCompany('RV Elekter OÜ', 'rv elekter'));
        $this->assertTrue(WarmClientService::sameCompany('AS Harju Elekter', 'Harju Elekter AS'));
        $this->assertTrue(WarmClientService::sameCompany('OÜ ETS-Elekter', 'ETS Elekter OÜ'));
    }

    public function test_different_companies_differ(): void
    {
        $this->assertFalse(WarmClientService::sameCompany('RV Elekter OÜ', 'AS Harju Elekter'));
        $this->assertFalse(WarmClientService::sameCompany('Asko OÜ', 'Ko OÜ'));
        $this->assertFalse(WarmClientService::sameCompany(null, ''));
    }
}
