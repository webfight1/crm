<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Quotation;
use App\Outreach\Models\OutreachLead;
use App\Seo\Jobs\HandleSeoReplyJob;
use App\Seo\Jobs\RunSeoAuditJob;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use App\Seo\Playbook;
use App\Seo\Services\ReplyIntentService;
use App\Seo\Services\SeoOfferService;
use App\Seo\Services\WarmClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SeoController extends Controller
{
    // ─── Playbook ───────────────────────────────────────────────────────────

    public function playbook(): View
    {
        $settings = [];
        foreach (Playbook::DEFINITIONS as $key => $def) {
            $settings[$def['section']][$key] = $def + ['value' => Playbook::get($key)];
        }

        return view('seo.playbook', [
            'sections' => Playbook::SECTIONS,
            'settings' => $settings,
            'checks'   => SeoAuditCheck::orderBy('sort_order')->orderBy('id')->get(),
            'funnel'   => $this->funnel(),
            'buckets'  => $this->positionBuckets(),
            'intents'  => $this->intentCounts(),
        ]);
    }

    public function updatePlaybook(Request $request): RedirectResponse
    {
        foreach (Playbook::DEFINITIONS as $key => $def) {
            $field = str_replace('.', '__', $key);
            if ($def['type'] === 'bool') {
                Playbook::set($key, $request->boolean($field) ? '1' : '0');
            } elseif ($request->has($field)) {
                $value = (string) $request->input($field, '');
                Playbook::set($key, $def['type'] === 'int' ? (string) (int) $value : $value);
            }
        }

        return back()->with('success', 'Playbook salvestatud. Muudatused kehtivad kohe.');
    }

    public function checksStore(Request $request): RedirectResponse
    {
        $data = $this->validateCheck($request, requireQuestion: true);
        $data['type'] = SeoAuditCheck::TYPE_AI;
        $data['key']  = 'ai_' . Str::slug(Str::limit($data['label'], 40, ''), '_') . '_' . Str::lower(Str::random(4));
        $data['sort_order'] = (int) SeoAuditCheck::max('sort_order') + 10;

        SeoAuditCheck::create($data);

        return back()->with('success', 'Kontroll lisatud.')->withFragment('checks');
    }

    public function checksUpdate(Request $request, SeoAuditCheck $check): RedirectResponse
    {
        $data = $this->validateCheck($request, requireQuestion: ! $check->isBuiltin());
        if ($check->isBuiltin()) {
            unset($data['question']);
        }
        $check->update($data);

        return back()->with('success', "„{$check->label}“ salvestatud.")->withFragment('checks');
    }

    public function checksDestroy(SeoAuditCheck $check): RedirectResponse
    {
        if ($check->isBuiltin()) {
            return back()->with('error', 'Sisseehitatud kontrolli ei saa kustutada — lülita see välja.');
        }
        $check->delete();

        return back()->with('success', 'Kontroll kustutatud.')->withFragment('checks');
    }

    private function validateCheck(Request $request, bool $requireQuestion): array
    {
        $data = $request->validate([
            'label'              => 'required|string|max:255',
            'question'           => ($requireQuestion ? 'required' : 'nullable') . '|string|max:1000',
            'client_explanation' => 'nullable|string|max:1000',
            'weight'             => 'required|integer|min:1|max:5',
            'fix_title'          => 'nullable|string|max:255',
            'fix_group'          => 'nullable|string|max:100',
            'fix_price'          => 'nullable|numeric|min:0',
            'fix_quantity'       => 'nullable|numeric|min:0',
            'fix_unit'           => 'nullable|string|max:50',
            'applies_to'         => 'nullable|in:' . implode(',', array_keys(SeoAuditCheck::APPLIES_TO)),
            'sort_order'         => 'nullable|integer|min:0',
        ]);
        $data['enabled']      = $request->boolean('enabled');
        $data['fix_quantity'] = $data['fix_quantity'] ?? 1;
        $data['fix_unit']     = ($data['fix_unit'] ?? null) ?: 'tk';
        $data['applies_to']   = ($data['applies_to'] ?? null) ?: 'all';
        if (! isset($data['sort_order'])) {
            unset($data['sort_order']);
        }

        return $data;
    }

    // ─── Hand-added warm client ─────────────────────────────────────────────

    public function warmStore(Request $request, WarmClientService $clients): RedirectResponse
    {
        $data = $request->validate([
            'company'     => 'required|string|max:255',
            'first_name'  => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'email'       => 'required|email|max:255',
            'website'     => 'required|string|max:255',
            'keyword'     => 'required|string|max:255',
            'position'    => 'nullable|integer|min:1|max:1000',
            'ranking_url' => 'nullable|url|max:500',
            'notes'       => 'nullable|string|max:2000',
        ]);

        $lead = $clients->addManual($data);
        HandleSeoReplyJob::dispatch($lead->id, manual: true);

        return redirect()->route('seo.audits.index')->with('success',
            "„{$data['company']}“ lisatud. Klient + tehing, audit ja täpsustuskirja mustand valmivad 1–2 minutiga — teade tuleb Telegrami.");
    }

    // ─── Audits ─────────────────────────────────────────────────────────────

    public function auditsIndex(): View
    {
        return view('seo.audits.index', [
            'audits' => SeoAudit::with(['lead', 'deal', 'quotation'])->latest()->paginate(30),
        ]);
    }

    public function auditsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url'     => 'required|string|max:255',
            'keyword'   => 'nullable|string|max:255',
            'deal_id'   => 'nullable|exists:deals,id',
            'site_type' => 'nullable|in:eshop,service',
        ]);
        if (! empty($data['site_type'])) {
            $data['site_type_note'] = 'Valitud käsitsi.';
        }

        $audit = SeoAudit::create($data);
        RunSeoAuditJob::dispatch($audit->id);

        return redirect()->route('seo.audits.show', $audit)
            ->with('success', 'Audit käivitatud — värskenda lehte umbes minuti pärast.');
    }

    public function auditsShow(SeoAudit $audit): View
    {
        $audit->load(['lead.campaign', 'deal.customer', 'quotation']);

        return view('seo.audits.show', [
            'audit' => $audit,
            'deals' => $audit->deal_id ? collect() : Deal::latest()->limit(50)->get(['id', 'title']),
        ]);
    }

    public function auditsRerun(SeoAudit $audit): RedirectResponse
    {
        $audit->update(['status' => SeoAudit::STATUS_PENDING]);
        RunSeoAuditJob::dispatch($audit->id);

        return back()->with('success', 'Audit käivitati uuesti.');
    }

    public function auditsAttachDeal(Request $request, SeoAudit $audit): RedirectResponse
    {
        $audit->update($request->validate(['deal_id' => 'required|exists:deals,id']));

        return back()->with('success', 'Tehing seotud.');
    }

    public function auditsOffer(SeoAudit $audit, SeoOfferService $offers): RedirectResponse
    {
        try {
            $quotation = $offers->createQuotation($audit, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('quotations.edit', $quotation)
            ->with('success', 'Pakkumise mustand koostatud auditi põhjal — vaata üle enne saatmist.');
    }

    // ─── Stats: what to tune next ────────────────────────────────────────────

    /** @return array<int, array{label:string, count:int}> */
    private function funnel(): array
    {
        $leads = fn () => OutreachLead::whereNotNull('serp_keyword');

        $quotationIds = SeoAudit::whereNotNull('quotation_id')->pluck('quotation_id');

        return [
            ['label' => 'Imporditud', 'count' => $leads()->count()],
            ['label' => 'Filter jättis välja', 'count' => $leads()->where('qualification', OutreachLead::QUALIFICATION_SKIP)->count()],
            ['label' => 'Kiri saadetud', 'count' => $leads()->where('current_step', '>=', 1)->count()],
            ['label' => 'Vastas', 'count' => $leads()->where('replied', true)->count()],
            ['label' => 'Soe klient', 'count' => $leads()->whereNotNull('deal_id')->count()],
            ['label' => 'Auditeeritud', 'count' => SeoAudit::where('status', SeoAudit::STATUS_DONE)->count()],
            ['label' => 'Pakkumine tehtud', 'count' => $quotationIds->count()],
            ['label' => 'Pakkumine vastu võetud', 'count' => Quotation::whereIn('id', $quotationIds)->where('status', 'accepted')->count()],
        ];
    }

    /** Reply rate by Google position — tells where to set the filter. */
    private function positionBuckets(): array
    {
        $bucket = 'CASE WHEN serp_position <= 10 THEN 1 WHEN serp_position <= 20 THEN 2 '
            . 'WHEN serp_position <= 30 THEN 3 WHEN serp_position <= 50 THEN 4 ELSE 5 END';

        $rows = OutreachLead::whereNotNull('serp_position')
            ->where('current_step', '>=', 1)
            ->selectRaw("{$bucket} AS b, COUNT(*) AS sent, SUM(CASE WHEN replied = 1 THEN 1 ELSE 0 END) AS replied")
            ->groupBy(DB::raw($bucket))
            ->get()->keyBy('b');

        $labels = [1 => '1–10', 2 => '11–20', 3 => '21–30', 4 => '31–50', 5 => '50+'];
        $out = [];
        foreach ($labels as $b => $label) {
            $sent = (int) ($rows[$b]->sent ?? 0);
            $replied = (int) ($rows[$b]->replied ?? 0);
            $out[] = ['label' => $label, 'sent' => $sent, 'replied' => $replied,
                      'rate' => $sent ? round($replied / $sent * 100, 1) : null];
        }

        return $out;
    }

    private function intentCounts(): array
    {
        $counts = OutreachLead::whereNotNull('reply_intent')
            ->selectRaw('reply_intent, COUNT(*) AS n')->groupBy('reply_intent')
            ->pluck('n', 'reply_intent');

        $out = [];
        foreach (ReplyIntentService::LABELS as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $out;
    }
}
