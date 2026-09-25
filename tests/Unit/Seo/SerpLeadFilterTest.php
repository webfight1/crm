<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\SerpLeadFilter;
use PHPUnit\Framework\TestCase;

class SerpLeadFilterTest extends TestCase
{
    public function test_maps_header_aliases_case_insensitively(): void
    {
        $f = new SerpLeadFilter(aliasLines: ['Märksõna = keyword', 'URL = website', 'broken line']);

        $this->assertSame(
            ['keyword', 'position', 'website', 'email'],
            $f->mapHeaders(['märksõna', 'position', 'url', 'email'])
        );
    }

    public function test_first_column_wins_when_two_map_to_same_field(): void
    {
        $f = new SerpLeadFilter(aliasLines: ['fraas = keyword']);

        $this->assertSame(['keyword', ''], $f->mapHeaders(['keyword', 'fraas']));
    }

    public function test_skip_rules(): void
    {
        $f = new SerpLeadFilter(11, 50, ['webfight.ee', 'konkurent']);

        $this->assertNull($f->skipReason(18, 'volt.ee', 'info@volt.ee'));
        $this->assertNull($f->skipReason(null, 'volt.ee', 'info@volt.ee'));
        $this->assertNotNull($f->skipReason(3, 'volt.ee', 'info@volt.ee'));
        $this->assertNotNull($f->skipReason(80, 'volt.ee', 'info@volt.ee'));
        $this->assertNotNull($f->skipReason(20, 'https://konkurent.ee', 'a@b.ee'));
        $this->assertNotNull($f->skipReason(20, null, 'veiko@webfight.ee'));
    }

    public function test_zero_limits_disable_position_rules(): void
    {
        $f = new SerpLeadFilter(0, 0);

        $this->assertNull($f->skipReason(1, 'x.ee', 'a@x.ee'));
        $this->assertNull($f->skipReason(99, 'x.ee', 'a@x.ee'));
    }
}
