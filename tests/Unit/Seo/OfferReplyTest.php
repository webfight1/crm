<?php

namespace Tests\Unit\Seo;

use App\Seo\Jobs\HandleOfferReplyJob;
use PHPUnit\Framework\TestCase;

class OfferReplyTest extends TestCase
{
    public function test_quotation_number_from_subject(): void
    {
        $this->assertSame('Q2026018', HandleOfferReplyJob::quotationNumber('Re: Pakkumine #Q2026018'));
        $this->assertSame('Q2026018', HandleOfferReplyJob::quotationNumber('SV: Q2026018 – küsimus'));
        $this->assertNull(HandleOfferReplyJob::quotationNumber('Re: Täpsustan kahte asja'));
        $this->assertNull(HandleOfferReplyJob::quotationNumber(null));
    }
}
