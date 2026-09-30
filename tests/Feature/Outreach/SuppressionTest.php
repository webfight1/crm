<?php

namespace Tests\Feature\Outreach;

use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachSuppression;
use App\Outreach\Services\OutreachCsvImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuppressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seo_pipeline' => false]);

        Schema::create('outreach_campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('outreach_leads', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->string('email');
            foreach (['first_name', 'last_name', 'company', 'website', 'industry', 'lcp_mobile', 'serp_keyword',
                      'serp_competitors', 'serp_url', 'notes', 'qualification', 'ai_line', 'reply_intent'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['performance_score', 'design_year', 'design_age', 'serp_position', 'serp_page'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->string('status')->default('active'); $t->integer('current_step')->default(0);
            $t->boolean('replied')->default(false);
            $t->timestamp('enrolled_at')->nullable(); $t->timestamp('next_send_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'email']);
        });
        Schema::create('outreach_suppressions', function (Blueprint $t) {
            $t->id(); $t->string('value')->unique(); $t->string('type', 10); $t->string('reason', 20);
            $t->unsignedBigInteger('lead_id')->nullable(); $t->string('note')->nullable(); $t->timestamps();
        });

        DB::table('outreach_campaigns')->insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
    }

    private function lead(int $campaign, string $email): OutreachLead
    {
        return OutreachLead::create(['campaign_id' => $campaign, 'email' => $email, 'status' => 'active']);
    }

    public function test_unsubscribe_in_one_campaign_stops_the_address_in_all(): void
    {
        $a = $this->lead(1, 'info@foo.ee');
        $b = $this->lead(2, 'info@foo.ee');
        $other = $this->lead(2, 'tere@bar.ee');

        $a->update(['status' => OutreachLead::STATUS_UNSUBSCRIBED]);

        $this->assertSame('unsubscribed', OutreachSuppression::firstWhere('value', 'info@foo.ee')->reason);
        $this->assertSame('unsubscribed', $b->fresh()->status);
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_bounce_and_not_interested_are_added(): void
    {
        $this->lead(1, 'x@foo.ee')->markBounced();
        $this->lead(1, 'y@foo.ee')->update(['reply_intent' => 'not_interested']);

        $this->assertSame(['x@foo.ee' => 'bounced', 'y@foo.ee' => 'not_interested'],
            OutreachSuppression::orderBy('value')->pluck('reason', 'value')->all());
    }

    public function test_domain_entry_blocks_every_address_of_the_domain(): void
    {
        $lead = $this->lead(1, 'juhataja@foo.ee');
        OutreachSuppression::add('https://www.Foo.ee/kontakt', 'manual');

        $this->assertSame('domain', OutreachSuppression::first()->type);
        $this->assertSame('foo.ee', OutreachSuppression::first()->value);
        $this->assertTrue(OutreachSuppression::isSuppressed('Keegi@FOO.ee'));
        $this->assertFalse(OutreachSuppression::isSuppressed('info@foo.eu'));
        $this->assertSame('unsubscribed', $lead->fresh()->status);
    }

    public function test_import_skips_suppressed_addresses(): void
    {
        OutreachSuppression::add('stop@foo.ee', 'manual');
        OutreachSuppression::add('bar.ee', 'manual');

        $csv = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($csv, "email;company\nstop@foo.ee;A\nkeegi@bar.ee;B\nok@baz.ee;C\n");

        $importer = app(OutreachCsvImportService::class);
        $count = $importer->import($csv, 2);
        @unlink($csv);

        $this->assertSame(1, $count);
        $this->assertSame(2, $importer->suppressed);
        $this->assertSame(['ok@baz.ee'], DB::table('outreach_leads')->pluck('email')->all());
    }
}
