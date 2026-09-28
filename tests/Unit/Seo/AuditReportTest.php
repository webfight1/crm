<?php

namespace Tests\Unit\Seo;

use App\Seo\Models\SeoAudit;
use App\Seo\Services\AuditReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditReportTest extends TestCase
{
    public function test_report_lists_fixes_first_with_the_reason_and_renders_a_pdf(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('seo_audit_checks', fn (Blueprint $t) => [$t->id(), $t->string('key'), $t->text('client_explanation')->nullable()]);
        \DB::table('seo_audit_checks')->insert(['key' => 'meta', 'client_explanation' => 'Google näitab seda otsingutulemuses.']);

        $audit = new SeoAudit(['url' => 'https://www.spsgrupp.ee/', 'keyword' => 'elektritööd', 'status' => SeoAudit::STATUS_DONE, 'score' => 55, 'results' => [
            ['key' => 'h1', 'label' => 'Lehel on H1', 'weight' => 5, 'status' => 'pass', 'note' => null, 'value' => 'Elektritööd'],
            ['key' => 'meta', 'label' => 'Meta kirjeldus', 'weight' => 5, 'status' => 'fail', 'note' => 'Puudub', 'value' => null],
            ['key' => 'speed', 'label' => 'Kiirus', 'weight' => 10, 'status' => 'fail', 'note' => null, 'value' => null, 'explanation' => 'Aeglane leht kaotab külastajaid.'],
        ]]);

        $report = app(AuditReport::class);
        [$s] = $report->sections(collect([$audit]));

        $this->assertSame(['Kiirus', 'Meta kirjeldus'], array_column($s['failed'], 'label'));
        $this->assertSame('Google näitab seda otsingutulemuses.', $s['failed'][1]['explanation']);
        $this->assertSame(['Lehel on H1'], array_column($s['passed'], 'label'));
        $this->assertSame('spsgrupp.ee', $report->site($audit));

        $html = view('seo.audits.report-pdf', ['site' => 'spsgrupp.ee', 'summary' => 'Kokkuvõte', 'sections' => [$s],
            'settings' => new \App\Models\Setting(['company_name' => 'Webfight OÜ'])])->render();
        $this->assertStringContainsString('Aeglane leht kaotab külastajaid.', $html);
        $this->assertStringStartsWith('%PDF', Pdf::loadHTML($html)->output());
    }
}
