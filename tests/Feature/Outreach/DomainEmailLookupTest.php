<?php

namespace Tests\Feature\Outreach;

use App\Services\DomainEmailLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainEmailLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.external_companies' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('external_companies');

        $s = Schema::connection('external_companies');
        $s->create('companies', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('regcode')->nullable(); $t->date('ended')->nullable();
        });
        foreach (['company_www' => 'www', 'company_emails' => 'email', 'company_phones' => 'phone'] as $table => $col) {
            $s->create($table, function (Blueprint $t) use ($col) {
                $t->id(); $t->unsignedBigInteger('company_id'); $t->string($col);
            });
        }

        $db = DB::connection('external_companies');
        $db->table('companies')->insert([
            ['id' => 1, 'name' => 'K Partner OÜ', 'regcode' => '111', 'ended' => null],
            ['id' => 2, 'name' => 'Kolija AS', 'regcode' => '222', 'ended' => null],
        ]);
        $db->table('company_www')->insert(['company_id' => 1, 'www' => 'https://www.kpartner.ee/']);
        $db->table('company_emails')->insert([
            ['company_id' => 1, 'email' => 'juhataja@gmail.com'],
            ['company_id' => 1, 'email' => 'info@kpartner.ee'],
            ['company_id' => 2, 'email' => 'tere@kolimisteenus.net'],
        ]);
        $db->table('company_phones')->insert(['company_id' => 1, 'phone' => '+372 5555 5555']);
    }

    public function test_normalize_domain(): void
    {
        $this->assertSame('foo.ee', DomainEmailLookupService::normalizeDomain('https://www.Foo.ee/kontakt?x=1'));
        $this->assertSame('foo.ee', DomainEmailLookupService::normalizeDomain('foo.ee'));
        $this->assertNull(DomainEmailLookupService::normalizeDomain('Katuse'));
        $this->assertNull(DomainEmailLookupService::normalizeDomain(''));
    }

    public function test_enriches_csv_by_www_and_email_domain(): void
    {
        $in = tempnam(sys_get_temp_dir(), 'in');
        $out = tempnam(sys_get_temp_dir(), 'out');
        file_put_contents($in, "\xEF\xBB\xBFDomeen;Koduleht;Märksõna\nkpartner.ee;https://kpartner.ee/;Katuse\nkolimisteenus.net;https://kolimisteenus.net/;kolimine\ntundmatu.ee;https://tundmatu.ee/;x\n");

        $stats = (new DomainEmailLookupService())->enrichFile($in, $out);

        $this->assertSame(['rows' => 3, 'found' => 2, 'domains' => 3], $stats);

        $lines = array_map(fn ($l) => str_getcsv($l, ';', '"', '\\'), explode("\n", trim(substr(file_get_contents($out), 3))));
        $this->assertSame(['Domeen', 'Koduleht', 'Märksõna', 'Email', 'Kõik emailid', 'Ettevõte', 'Registrikood', 'Telefon', 'Leitud'], $lines[0]);
        $this->assertSame(['kpartner.ee', 'https://kpartner.ee/', 'Katuse', 'info@kpartner.ee', 'info@kpartner.ee, juhataja@gmail.com', 'K Partner OÜ', '111', '+372 5555 5555', 'www'], $lines[1]);
        $this->assertSame('tere@kolimisteenus.net', $lines[2][3]);
        $this->assertSame('e-posti domeen', $lines[2][8]);
        $this->assertSame('', $lines[3][3]);

        @unlink($in);
        @unlink($out);
    }

    public function test_rows_without_email_go_to_separate_file(): void
    {
        $in = tempnam(sys_get_temp_dir(), 'in');
        $out = tempnam(sys_get_temp_dir(), 'out');
        $missing = tempnam(sys_get_temp_dir(), 'miss');
        file_put_contents($in, "Domeen,Märksõna\nkpartner.ee,Katuse\ntundmatu.ee,x\n");

        (new DomainEmailLookupService())->enrichFile($in, $out, $missing);

        $found = explode("\n", trim(substr(file_get_contents($out), 3)));
        $rest = explode("\n", trim(substr(file_get_contents($missing), 3)));
        $this->assertCount(2, $found);
        $this->assertStringStartsWith('kpartner.ee,', $found[1]);
        $this->assertCount(2, $rest);
        $this->assertStringStartsWith('tundmatu.ee,', $rest[1]);
        $this->assertSame($found[0], $rest[0]);

        @unlink($in);
        @unlink($out);
        @unlink($missing);
    }
}
