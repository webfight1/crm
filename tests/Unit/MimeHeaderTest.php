<?php

namespace Tests\Unit;

use App\Outreach\Support\MimeHeader;
use PHPUnit\Framework\TestCase;

class MimeHeaderTest extends TestCase
{
    public function test_emoji_split_across_encoded_words(): void
    {
        $raw = "=?utf-8?Q?Re:_Mis_v=C3=B5iks_sel_aastal_kingikotti_j=C3=B5uda=3F_=F0=9F?=\r\n =?utf-8?Q?=8E=81?=";

        $this->assertSame('Re: Mis võiks sel aastal kingikotti jõuda? 🎁', MimeHeader::decode($raw));
    }

    public function test_mixed_plain_and_encoded_text(): void
    {
        $this->assertSame('Re: Tere Jüri', MimeHeader::decode('Re: =?UTF-8?B?VGVyZSBKw7xyaQ==?='));
        $this->assertSame('a b', MimeHeader::decode('=?utf-8?Q?a?= =?utf-8?Q?_b?='));
    }

    public function test_other_charsets_and_plain_values(): void
    {
        $this->assertSame('Jõulud', MimeHeader::decode('=?iso-8859-1?Q?J=F5ulud?='));
        $this->assertSame('Tavaline teema', MimeHeader::decode('Tavaline teema'));
        $this->assertSame('', MimeHeader::decode(null));
    }
}
