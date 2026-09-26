<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\ExternalCompany;
use App\Models\Quotation;
use App\Outreach\Models\OutreachLead;
use App\Seo\Jobs\HandleSeoClarifyAnswerJob;
use App\Seo\Jobs\HandleSeoReplyJob;
use App\Seo\Jobs\RunSeoAuditJob;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use App\Seo\Playbook;
use App\Seo\Services\ReplyIntentService;
use App\Seo\Services\SeoOfferService;
use App\Seo\Services\WarmClientService;
use Illuminate\Http\JsonResponse;
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

    /** Every SEO client, stage by stage (PipelineBoard). */
    public function clients(Request $request, \App\Seo\Services\PipelineBoard $board): View
    {
        $rows = collect($board->rows());
        $counts = [
            'me'     => $rows->where('closed', false)->where('waiting', 'me')->count(),
            'client' => $rows->where('closed', false)->where('waiting', 'client')->count(),
            'done'   => $rows->where('done', true)->count(),
            'closed' => $rows->where('closed', true)->count(),
        ];

        $filter = $request->string('f')->toString() ?: 'open';
        $rows = match ($filter) {
            'me', 'client' => $rows->where('closed', false)->where('waiting', $filter),
            'done'   => $rows->where('done', true),
            'closed' => $rows->where('closed', true),
            'all'    => $rows,
            default  => $rows->where('closed', false)->where('done', false),
        };

        return view('seo.clients', [
            'rows'   => $rows->values(),
            'filter' => $filter,
            'counts' => $counts,
            'stages' => \App\Seo\Services\PipelineBoard::STAGES,
        ]);
    }

    /** docs/seo-automation.md (process + roadmap) rendered read-only. */
    public function docs(): View
    {
        $path = base_path('docs/seo-automation.md');

        return view('seo.docs', [
            'html'      => is_file($path)
                ? Str::markdown((string) file_get_contents($path), ['html_input' => 'strip', 'allow_unsafe_links' => false])
                : '<p>Faili docs/seo-automation.md ei leitud.</p>',
            'updatedAt' => is_file($path) ? \Carbon\Carbon::createFromTimestamp(filemtime($path)) : null,
        ]);
    }

    // ─── Hand-added warm client ─────────────────────────────────────────────

    /**
     * Company autocomplete for the warm-client form: CRM companies, CRM
     * customers, then the business register (external DB, may be offline).
     */
    public function companySearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $out = [];

        Company::with(['customers' => fn ($c) => $c->orderBy('id')])
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('registrikood', 'like', $like))
            ->orderBy('name')->limit(6)->get()
            ->each(function (Company $c) use (&$out) {
                $p = $c->customers->first();
                $out[] = [
                    'source' => 'crm', 'company' => $c->name, 'registrikood' => $c->registrikood,
                    'first_name' => $p?->first_name, 'last_name' => $p?->last_name,
                    'email' => $p?->email ?: $c->email, 'website' => $c->website,
                ];
            });

        Customer::with('company')
            ->where(fn ($w) => $w->where('email', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", [$like]))
            ->limit(5)->get()
            ->each(function (Customer $p) use (&$out) {
                $out[] = [
                    'source' => 'customer', 'company' => $p->company?->name ?: '', 'registrikood' => $p->company?->registrikood,
                    'first_name' => $p->first_name, 'last_name' => $p->last_name,
                    'email' => $p->email, 'website' => $p->company?->website,
                ];
            });

        try {
            $known = array_filter(array_column($out, 'registrikood'));
            foreach (ExternalCompany::searchByName($q, 6) as $e) {
                if (in_array($e->regcode, $known, false)) {
                    continue;
                }
                $extra = $e->getAdditionalData();
                $out[] = [
                    'source' => 'register', 'company' => $e->name, 'registrikood' => $e->regcode,
                    'first_name' => null, 'last_name' => null,
                    'email' => $extra['emails'][0] ?? null, 'website' => $extra['websites'][0] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Business register DB unavailable — CRM results are still useful.
        }

        return response()->json($out);
    }

    public function warmStore(Request $request, WarmClientService $clients): RedirectResponse
    {
        $data = $request->validate([
            'company'     => 'required|string|max:255',
            'registrikood' => 'nullable|string|max:20',
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
        $audit->load(['lead.campaign', 'deal.customer', 'quotation', 'mainAudit']);
        $root = $audit->root();
        $siblings = collect([$root])->concat($root->pages()->get());

        // Extra keywords from the clarification answer ("kw | url") not audited yet.
        $audited = $siblings->pluck('keyword')->filter()->map(fn ($k) => mb_strtolower($k))->all();
        $suggested = collect(\App\Seo\Playbook::parseLines((string) $audit->lead?->seo_extra_keywords))
            ->reject(fn ($line) => in_array(mb_strtolower(trim(explode('|', $line)[0])), $audited, true))
            ->map(fn ($line) => trim(implode(' | ', array_reverse(array_filter(array_map('trim', explode('|', $line)))))))
            ->implode("\n");

        return view('seo.audits.show', [
            'audit' => $audit,
            'root' => $root,
            'siblings' => $siblings,
            'suggestedPages' => $suggested,
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

    /**
     * More pages / keywords of the same client: one audit per line, hanging
     * off the main audit (same lead + deal → one combined quotation).
     * Line: "URL | märksõna", "märksõna | URL", "URL" or just "märksõna"
     * (then the page is looked up on the client's site).
     */
    public function auditsAddPages(Request $request, SeoAudit $audit): RedirectResponse
    {
        $root = $audit->root();
        $lines = array_slice(\App\Seo\Playbook::parseLines((string) $request->validate(['pages' => 'required|string|max:5000'])['pages']), 0, 10);
        $site = \App\Seo\Services\SiteCrawler::origin($audit->lead?->website ?: $root->url);

        $created = 0;
        foreach ($lines as $line) {
            $url = $keyword = null;
            foreach (array_filter(array_map('trim', explode('|', $line))) as $part) {
                if (! $url && preg_match('~^(https?://|www\.|/)|^[^\s]+\.[a-z]{2,}(/|$)~i', $part)) {
                    $url = $part;
                } elseif (! $keyword) {
                    $keyword = $part;
                }
            }
            if ($url && str_starts_with($url, '/')) {
                $url = $site ? rtrim($site, '/') . $url : null;
            } elseif ($url && ! preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            $url ??= $site ? rtrim($site, '/') . '/' : null; // keyword only → find its page on the site
            if (! $url) {
                continue;
            }

            $page = SeoAudit::create([
                'lead_id'        => $root->lead_id,
                'main_audit_id'  => $root->id,
                'deal_id'        => $root->deal_id,
                'url'            => mb_substr($url, 0, 255),
                'keyword'        => $keyword ? mb_substr($keyword, 0, 255) : ($root->keyword ?: null),
                'site_type'      => $root->site_type,
                'site_type_note' => $root->site_type ? 'Sama sait mis põhiauditis.' : null,
            ]);
            RunSeoAuditJob::dispatch($page->id);
            $created++;
        }

        return redirect()->route('seo.audits.show', $root)->with(
            $created ? 'success' : 'error',
            $created ? "Auditeerin {$created} lehte — värskenda lehte paari minuti pärast. Siis „Uuenda pakkumist“ paneb kõik ühte pakkumisse." : 'Ühtegi lehte ei leitud — kirjuta rea kaupa „URL | märksõna“.'
        );
    }

    /** Operator edits the client-facing summary; a still-draft quotation gets it too. */
    public function auditsSummary(Request $request, SeoAudit $audit, SeoOfferService $offers): RedirectResponse
    {
        $summary = trim($request->validate(['summary' => 'nullable|string|max:20000'])['summary'] ?? '');
        $audit->update(['summary' => $summary !== '' ? $summary : null]);

        // Only the main audit's summary goes into the quotation.
        $quotation = $audit->main_audit_id ? null : $audit->quotation;
        if ($quotation && $quotation->status === 'draft') {
            $pages = $audit->pages()->where('status', SeoAudit::STATUS_DONE)->get()->all();
            $quotation->update(['description' => $offers->description($audit, $pages)]);

            return back()->with('success', "Kokkuvõte salvestatud ja uuendatud ka pakkumise {$quotation->number} mustandis.");
        }

        return back()->with('success', 'Kokkuvõte salvestatud.' . ($quotation ? " Pakkumine {$quotation->number} on juba saadetud — seda ei muudetud." : ''));
    }

    /** Operator: the client's latest mail IS the clarification answer. */
    public function auditsClarifyAnswer(SeoAudit $audit): RedirectResponse
    {
        if ($audit->lead?->seo_stage !== 'awaiting_answer') {
            return back()->with('error', 'See klient ei oota täpsustuse vastust.');
        }
        HandleSeoClarifyAnswerJob::dispatch($audit->lead->id, force: true);

        return back()->with('success', 'Kliendi viimane kiri läks töötlusse täpsustuse vastusena — tulemus tuleb Telegrami.');
    }

    /** Operator: no clarification e-mail for this client — go straight to the offer. */
    public function auditsClarifySkip(SeoAudit $audit): RedirectResponse
    {
        $lead = $audit->lead;
        if (! $lead || ! in_array($lead->seo_stage, [null, 'clarify_drafted', 'awaiting_answer'], true)) {
            return back()->with('error', 'Täpsustuse etapp on sellel kliendil juba möödas.');
        }
        $lead->update(['seo_stage' => 'clarify_skipped', 'seo_clarify_body' => null]);

        return back()->with('success', 'Täpsustuskiri jäeti vahele — järgmine samm on pakkumine.');
    }

    public function auditsAccess(SeoAudit $audit, \App\Seo\Services\AccessRequestService $access): RedirectResponse
    {
        if (! $audit->lead) {
            return back()->with('error', 'Auditil pole leadi — ligipääsukirja saab teha ainult SEO-kliendile.');
        }
        if (! $access->request($audit->lead, auth()->id())) {
            return back()->with('error', 'Ligipääse on sellelt kliendilt juba küsitud.');
        }

        return redirect()->to(\App\Outreach\Models\OutreachMessage::inboxThreadUrl($audit->lead->email))
            ->with('success', 'Ligipääsukirja mustand on vastamisvormis ja ülesanne loodud — vaata üle ja saada.');
    }

    public function auditsAccessGranted(SeoAudit $audit, \App\Seo\Services\AccessRequestService $access): RedirectResponse
    {
        if (! $audit->lead) {
            return back()->with('error', 'Auditil pole leadi.');
        }
        $access->markGranted($audit->lead, auth()->id());

        return back()->with('success', 'Ligipääsud märgitud olemasolevaks — kirja ei saadeta ja „töös“ ei küsi neid uuesti.');
    }

    public function auditsOffer(Request $request, SeoAudit $audit, SeoOfferService $offers): RedirectResponse
    {
        try {
            $quotation = $offers->createQuotation($audit, auth()->id(), $request->boolean('rebuild'));
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
