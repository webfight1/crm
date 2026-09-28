<?php

namespace Tests\Unit\Seo;

use App\Models\Deal;
use App\Models\Task;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Seo\Models\SeoEvent;
use App\Seo\Services\ClientLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        // Only the columns the log reads (the real migrations are MySQL-only).
        config(['services.seo_monitor' => ['api_url' => 'https://seo.test/api/v1', 'token' => 't', 'app_url' => 'https://seo.test']]);
        \Illuminate\Support\Facades\Http::fake(['seo.test/api/v1/projects/7/report/sends' => \Illuminate\Support\Facades\Http::response(['data' => [
            ['sent_at' => '2026-09-21T11:00:00+00:00', 'recipients' => ['a@firma.ee'], 'subject' => 'SEO raport', 'message' => 'Tere!', 'user' => 'Veiko'],
        ]])]);
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('outreach_leads', function (Blueprint $t) {
            $t->id(); $t->string('email')->nullable(); $t->string('company')->nullable(); $t->string('serp_keyword')->nullable();
            $t->string('seo_stage')->nullable(); $t->unsignedBigInteger('seo_monitor_project_id')->nullable();
            $t->unsignedBigInteger('deal_id')->nullable(); $t->boolean('replied')->default(false); $t->timestamps();
        });
        Schema::create('outreach_send_logs', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('lead_id'), $t->string('subject'), $t->string('status'), $t->timestamp('sent_at')->nullable(), $t->timestamps()]);
        Schema::create('outreach_messages', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('lead_id')->nullable(), $t->string('direction'), $t->string('subject')->nullable(), $t->text('body_text')->nullable(), $t->timestamp('received_at')->nullable(), $t->timestamps()]);
        Schema::create('seo_audits', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('lead_id')->nullable(), $t->unsignedBigInteger('main_audit_id')->nullable(), $t->string('url'), $t->string('keyword')->nullable(), $t->string('status'), $t->integer('score')->nullable(), $t->text('error')->nullable(), $t->timestamp('completed_at')->nullable(), $t->timestamps(), $t->softDeletes()]);
        Schema::create('deals', fn (Blueprint $t) => [$t->id(), $t->string('title'), $t->string('stage'), $t->timestamps()]);
        Schema::create('quotations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('deal_id')->nullable(), $t->string('number'), $t->string('status'), $t->decimal('total', 10, 2)->default(0), $t->timestamps(), $t->softDeletes()]);
        Schema::create('quotation_email_sends', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('quotation_id'), $t->string('to_email'), $t->string('subject')->nullable(), $t->timestamp('sent_at')->nullable(), $t->timestamps()]);
        Schema::create('tasks', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('deal_id')->nullable(), $t->string('title'), $t->string('status')->default('pending'), $t->timestamp('completed_at')->nullable(), $t->timestamps(), $t->softDeletes()]);
        Schema::create('time_entries', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('task_id'), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamp('start_time')->nullable(), $t->decimal('duration', 8, 2)->nullable(), $t->text('notes')->nullable(), $t->timestamps()]);
        Schema::create('seo_events', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('lead_id'), $t->string('type'), $t->string('title'), $t->text('body')->nullable(), $t->string('url')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamp('created_at')->nullable()]);
    }

    public function test_stage_changes_are_logged_and_merged_with_mail_tasks_and_time(): void
    {
        Carbon::setTestNow('2026-09-20 10:00');
        $deal = Deal::forceCreate(['title' => 'SEO — Firma', 'stage' => 'qualified']);
        $lead = OutreachLead::forceCreate(['email' => 'a@firma.ee', 'serp_keyword' => 'elektritööd', 'deal_id' => $deal->id, 'seo_monitor_project_id' => 7]);

        Carbon::setTestNow('2026-09-21 09:00');
        OutreachMessage::forceCreate(['lead_id' => $lead->id, 'direction' => 'inbound', 'subject' => 'Re: pakkumine', 'received_at' => now()]);
        $lead->update(['seo_stage' => 'clarify_skipped']);

        Carbon::setTestNow('2026-09-22 12:00');
        $deal->update(['stage' => 'töös']);
        $task = Task::forceCreate(['deal_id' => $deal->id, 'title' => 'Meta kirjeldused', 'status' => 'completed', 'completed_at' => now()->addHour()]);
        \App\Models\TimeEntry::forceCreate(['task_id' => $task->id, 'start_time' => now(), 'duration' => 1.5, 'notes' => 'avaleht + teenused']);
        ClientLog::record($lead->id, 'note', 'Helistasin kliendile', at: Carbon::parse('2026-09-19 15:00'));

        $this->assertSame(3, SeoEvent::where('lead_id', $lead->id)->count());

        $titles = app(ClientLog::class)->entries($lead)->pluck('title')->all();
        $this->assertSame('Tehtud: Meta kirjeldused', $titles[0]);
        $this->assertContains('Tööaeg 1:30 h — Meta kirjeldused', $titles);
        $this->assertContains('Tehing: Kvalifitseeritud → Töös', $titles);
        $this->assertContains('SEO raport saadetud: a@firma.ee', $titles);
        $this->assertContains('Täpsustus jäeti vahele', $titles);
        $this->assertContains('Klient kirjutas: Re: pakkumine', $titles);
        $this->assertSame('Helistasin kliendile', end($titles));

        Carbon::setTestNow('2026-09-23 08:00');
        $task->update(['status' => 'in_progress']);
        Carbon::setTestNow('2026-09-23 08:30');
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $titles = app(ClientLog::class)->entries($lead)->pluck('title')->all();
        $this->assertSame('Ülesanne „Meta kirjeldused“: Töös → Valmis', $titles[0]);
        $this->assertContains('Ülesanne „Meta kirjeldused“: Valmis → Töös', $titles);
        $this->assertNotContains('Tehtud: Meta kirjeldused', $titles);
    }
}
