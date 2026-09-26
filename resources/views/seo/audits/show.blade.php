<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Audit: {{ $audit->lead?->company ?: parse_url($audit->url, PHP_URL_HOST) ?: $audit->url }}
            </h2>
            <a href="{{ route('seo.audits.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Kõik auditid</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6 grid md:grid-cols-4 gap-6">
                <div class="md:col-span-3 space-y-1 text-sm">
                    <p><span class="text-gray-500">Leht:</span> <a href="{{ $audit->url }}" target="_blank" rel="noopener" class="text-indigo-600">{{ $audit->url }}</a></p>
                    <p><span class="text-gray-500">Märksõna:</span> {{ $audit->keyword ?: '—' }}
                        @if($audit->lead?->serp_position) · koht {{ $audit->lead->serp_position }} ({{ $audit->lead->serp_page }}. leht) @endif</p>
                    @if($audit->page_source)
                        <p><span class="text-gray-500">Lehe valik:</span>
                            <span class="{{ $audit->page_source === 'none' ? 'text-red-600' : '' }}">{{ \App\Seo\Services\SeoAuditService::PAGE_SOURCES[$audit->page_source] ?? $audit->page_source }}</span>
                            @if($audit->page_note)<span class="text-gray-500"> · {{ $audit->page_note }}</span>@endif
                        </p>
                    @endif
                    @if($audit->site_type)
                        <p><span class="text-gray-500">Saidi tüüp:</span>
                            <strong>{{ \App\Seo\Services\SiteTypeDetector::LABELS[$audit->site_type] ?? $audit->site_type }}</strong>
                            @if($audit->site_type_note)<span class="text-gray-500"> · {{ $audit->site_type_note }}</span>@endif
                        </p>
                    @endif
                    @if($blog = $audit->extras['blog'] ?? null)
                        <p><span class="text-gray-500">Blogi:</span>
                            @if($blog['exists'])
                                <strong>olemas</strong>@if($blog['url']) · <a href="{{ $blog['url'] }}" target="_blank" rel="noopener" class="text-indigo-600">{{ $blog['url'] }}</a>@endif
                                · pakkumisse: igakuised artiklid
                            @else
                                <strong class="text-amber-700">puudub</strong> · pakkumisse: blogi loomine
                            @endif
                            <span class="text-gray-500"> · {{ $blog['note'] }} (kehtib, kui skoor ≥ {{ \App\Seo\Playbook::int('content.min_score') }})</span>
                        </p>
                    @endif
                    @if($hosting = $audit->extras['hosting'] ?? null)
                        <p><span class="text-gray-500">Majutus:</span>
                            <strong class="{{ $hosting['provider'] ? '' : 'text-amber-700' }}">{{ $hosting['provider'] ?? 'teadmata' }}</strong>
                            <span class="text-gray-500"> · {{ $hosting['note'] }}</span>
                        </p>
                    @endif
                    @if($audit->lead?->seo_monitor_project_id)
                        <p><span class="text-gray-500">SEO-monitor:</span>
                            <a href="{{ app(\App\Seo\Services\SeoMonitorClient::class)->projectUrl($audit->lead->seo_monitor_project_id) }}" target="_blank" rel="noopener" class="text-indigo-600">projekt #{{ $audit->lead->seo_monitor_project_id }} →</a>
                        </p>
                    @endif
                    @if($audit->lead?->seo_stage)
                        <p><span class="text-gray-500">Täpsustuskiri:</span>
                            {{ ['clarify_drafted' => 'mustand postkastis, saatmata', 'awaiting_answer' => 'saadetud, ootab vastust', 'answered' => 'klient vastas', 'clarify_skipped' => 'vahele jäetud', 'access_drafted' => 'ligipääsukirja mustand postkastis', 'access_requested' => 'ligipääsud küsitud', 'access_granted' => 'ligipääsud olemas'][$audit->lead->seo_stage] ?? $audit->lead->seo_stage }}
                            · <a href="{{ \App\Outreach\Models\OutreachMessage::inboxThreadUrl($audit->lead->email) }}" class="text-indigo-600">postkast →</a>
                        </p>
                    @endif
                    @if($audit->lead?->seo_extra_keywords)
                        <div><span class="text-gray-500">Kliendi lisamärksõnad:</span>
                            <ul class="ml-4 list-disc">
                                @foreach(\App\Seo\Playbook::parseLines($audit->lead->seo_extra_keywords) as $line)
                                    @php [$kw, $page] = array_pad(array_map('trim', explode('|', $line, 2)), 2, ''); @endphp
                                    <li>{{ $kw }} →
                                        @if($page)<a href="{{ $page }}" target="_blank" rel="noopener" class="text-indigo-600">{{ $page }}</a>
                                        @else<span class="text-red-600">oma leht puudub</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($audit->lead)
                        <p><span class="text-gray-500">Lead:</span> {{ $audit->lead->email }} · {{ $audit->lead->campaign?->name }}
                            @if($audit->lead->reply_intent) · vastus: <strong>{{ \App\Seo\Services\ReplyIntentService::LABELS[$audit->lead->reply_intent] ?? $audit->lead->reply_intent }}</strong>@endif
                        </p>
                    @endif
                    <p><span class="text-gray-500">Tehing:</span>
                        @if($audit->deal)
                            <a href="{{ route('deals.show', $audit->deal) }}" class="text-indigo-600">{{ $audit->deal->title }}</a>
                        @else
                            <form method="POST" action="{{ route('seo.audits.deal', $audit) }}" class="inline-flex gap-2 items-center">
                                @csrf
                                <select name="deal_id" class="text-sm border-gray-300 rounded-md py-1">
                                    @foreach($deals as $d)<option value="{{ $d->id }}">{{ $d->title }}</option>@endforeach
                                </select>
                                <button class="text-xs text-indigo-600">Seo</button>
                            </form>
                        @endif
                    </p>
                    <p><span class="text-gray-500">Pakkumine:</span>
                        @if($audit->quotation)
                            <a href="{{ route('quotations.edit', $audit->quotation) }}" class="text-indigo-600">{{ $audit->quotation->number }}</a>
                            ({{ $audit->quotation->status }}, {{ number_format((float) $audit->quotation->total, 2, ',', ' ') }} €)
                        @else — @endif
                    </p>
                </div>
                <div class="text-center">
                    @if($audit->status === 'done')
                        @php $s = $audit->score ?? 0; @endphp
                        <div class="text-5xl font-bold {{ $s >= 80 ? 'text-green-600' : ($s >= 50 ? 'text-yellow-600' : 'text-red-600') }}">{{ $audit->score ?? '—' }}</div>
                        <div class="text-xs text-gray-500">/ 100</div>
                    @elseif($audit->status === 'failed')
                        <div class="text-red-600 text-sm">Ebaõnnestus</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $audit->error }}</div>
                    @else
                        <div class="text-yellow-600 text-sm">Töös…</div>
                        <div class="text-xs text-gray-500 mt-1">Värskenda lehte hetke pärast.</div>
                    @endif
                    <div class="mt-4 space-y-2">
                        <form method="POST" action="{{ route('seo.audits.rerun', $audit) }}">@csrf
                            <button class="w-full text-xs border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">Käivita uuesti</button>
                        </form>
                        @if($audit->lead?->seo_stage === 'awaiting_answer')
                            <form method="POST" action="{{ route('seo.audits.clarify-answer', $audit) }}">@csrf
                                <button class="w-full text-xs border border-gray-300 rounded px-2 py-1 hover:bg-gray-50" title="Kliendi viimane kiri loetakse täpsustuskirja vastuseks">✉️ Töötle täpsustuse vastusena</button>
                            </form>
                        @endif
                        @if($audit->lead && in_array($audit->lead->seo_stage, [null, 'clarify_drafted', 'awaiting_answer'], true))
                            <form method="POST" action="{{ route('seo.audits.clarify-skip', $audit) }}"
                                  onsubmit="return confirm('Jätta täpsustuskiri saatmata ja minna otse pakkumise juurde?')">@csrf
                                <button class="w-full text-xs border border-gray-300 rounded px-2 py-1 hover:bg-gray-50" title="Täpsustuskirja ei saadeta — tahvlil läheb etapp roheliseks ja järgmine samm on pakkumine">⏭ Jäta täpsustus vahele</button>
                            </form>
                        @endif
                        @if($audit->lead && ! in_array($audit->lead->seo_stage, ['access_drafted', 'access_requested', 'access_granted'], true))
                            <form method="POST" action="{{ route('seo.audits.access', $audit) }}">@csrf
                                <button class="w-full text-xs border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">🔑 Küsi ligipääsud</button>
                            </form>
                        @endif
                        @if($audit->lead && $audit->lead->seo_stage !== 'access_granted')
                            <form method="POST" action="{{ route('seo.audits.access-granted', $audit) }}"
                                  onsubmit="return confirm('Ligipääsud on sul juba olemas? Kirja ei saadeta ja ligipääsu ülesanne märgitakse tehtuks.')">@csrf
                                <button class="w-full text-xs border border-gray-300 rounded px-2 py-1 hover:bg-gray-50" title="Vana klient — ligipääsud juba käes">✔️ Ligipääsud juba olemas</button>
                            </form>
                        @endif
                        @if($root->status === 'done' && ! $root->quotation_id && $root->deal_id)
                            <form method="POST" action="{{ route('seo.audits.offer', $root) }}">@csrf
                                <x-primary-button class="w-full justify-center">Koosta pakkumine{{ $siblings->count() > 1 ? ' (' . $siblings->count() . ' lehte)' : '' }}</x-primary-button>
                            </form>
                        @elseif($root->quotation?->status === 'draft' && $siblings->count() > 1)
                            <form method="POST" action="{{ route('seo.audits.offer', $root) }}">@csrf
                                <input type="hidden" name="rebuild" value="1">
                                <button class="w-full text-xs border border-indigo-300 text-indigo-700 rounded px-2 py-1 hover:bg-indigo-50"
                                        title="Pakkumise mustandi read ja kirjeldus koostatakse uuesti kõigi lehtede põhjal (käsitsi muudatused ridades kaovad)">🔄 Uuenda pakkumist ({{ $siblings->count() }} lehte)</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- All pages of this client: the main audit + extra pages/keywords. --}}
            <div class="bg-white shadow-sm rounded-lg p-6" x-data="{ adding: false }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">Kliendi lehed ({{ $siblings->count() }})</h3>
                    <button type="button" x-show="! adding" @click="adding = true" class="text-sm text-indigo-600 hover:text-indigo-800">+ Lisa lehti / märksõnu</button>
                </div>
                <table class="min-w-full text-sm">
                    <tbody>
                    @foreach($siblings as $sib)
                        <tr class="border-t border-gray-100 {{ $sib->id === $audit->id ? 'bg-indigo-50' : '' }}">
                            <td class="px-2 py-1.5 w-20 text-xs text-gray-500">{{ $sib->main_audit_id ? 'lisaleht' : 'põhileht' }}</td>
                            <td class="px-2 py-1.5 break-all">
                                <a href="{{ route('seo.audits.show', $sib) }}" class="text-indigo-600 hover:text-indigo-800">{{ $sib->url }}</a>
                                @if($sib->keyword)<span class="text-gray-500"> · „{{ $sib->keyword }}“</span>@endif
                            </td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">
                                @if($sib->status === 'done')<strong>{{ $sib->score }}</strong>/100
                                @elseif($sib->status === 'failed')<span class="text-red-600 text-xs">ebaõnnestus</span>
                                @else<span class="text-yellow-600 text-xs">töös…</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <form x-show="adding" x-cloak method="POST" action="{{ route('seo.audits.pages', $audit) }}" class="mt-4 space-y-2">
                    @csrf
                    <textarea name="pages" rows="5" placeholder="https://klient.ee/teenus | märksõna&#10;teine märksõna&#10;/kontakt" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $suggestedPages }}</textarea>
                    <p class="text-xs text-gray-500">
                        Üks rida = üks leht: „URL | märksõna“, ainult URL või ainult märksõna (siis otsitakse lehte kliendi saidilt). Kuni 10 rida.
                        @if($suggestedPages) Eeltäidetud kliendi täpsustuskirja lisamärksõnadega. @endif
                        Kõik lehed lähevad ühte pakkumisse: kogu saiti puudutavad parandused ühe korra, iga lehe omad eraldi real.
                    </p>
                    <div class="flex gap-2">
                        <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">Auditeeri lehed</button>
                        <button type="button" @click="adding = false" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Tühista</button>
                    </div>
                </form>
            </div>

            @if($audit->summary || $audit->status === 'done')
                <div class="bg-white shadow-sm rounded-lg p-6" x-data="{ editing: false }">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold">Kokkuvõte kliendile</h3>
                        <button type="button" x-show="! editing" @click="editing = true" class="text-sm text-indigo-600 hover:text-indigo-800">✏️ Muuda</button>
                    </div>
                    <div x-show="! editing" class="text-sm text-gray-800 whitespace-pre-line">{{ $audit->summary ?: '—' }}</div>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('seo.audits.summary', $audit) }}" class="space-y-2">
                        @csrf
                        <textarea name="summary" rows="12" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $audit->summary }}</textarea>
                        <p class="text-xs text-gray-500">
                            @if($audit->main_audit_id) Lisalehe kokkuvõte — pakkumisse läheb põhilehe oma. @else Läheb pakkumise kirjeldusse{{ $audit->quotation && $audit->quotation->status === 'draft' ? " (uuendatakse ka mustandis {$audit->quotation->number})" : '' }}. @endif
                            Auditi uuesti käivitamine kirjutab selle üle.
                        </p>
                        <div class="flex gap-2">
                            <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">Salvesta</button>
                            <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Tühista</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($audit->results)
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase text-left">
                            <tr><th class="px-4 py-2 w-24">Tulemus</th><th class="px-4 py-2">Kontroll</th><th class="px-4 py-2">Leitud</th><th class="px-4 py-2 text-right">Kaal</th><th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody>
                        @php
                            $taskDeal = $audit->deal_id ?? $audit->lead?->deal_id;
                            $taskSite = parse_url($audit->url, PHP_URL_HOST) ?: $audit->url;
                        @endphp
                        @foreach($audit->results as $r)
                            <tr class="border-t border-gray-100 align-top">
                                <td class="px-4 py-2">
                                    @if($r['status'] === 'pass')<span class="text-green-700 bg-green-50 px-2 py-0.5 rounded text-xs">OK</span>
                                    @elseif($r['status'] === 'fail')<span class="text-red-700 bg-red-50 px-2 py-0.5 rounded text-xs">Puudus</span>
                                    @else<span class="text-gray-500 bg-gray-100 px-2 py-0.5 rounded text-xs">Vahele</span>@endif
                                </td>
                                <td class="px-4 py-2">
                                    {{ $r['label'] }}
                                    @if(($r['type'] ?? '') === 'ai')<span class="ml-1 text-xs text-purple-600">AI</span>@endif
                                </td>
                                <td class="px-4 py-2 text-gray-600 break-all">
                                    @if($r['note'])<div>{{ $r['note'] }}</div>@endif
                                    @if($r['value'] !== null && $r['value'] !== '')<div class="text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($r['value'], 160) }}</div>@endif
                                </td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $r['weight'] }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    {{-- Track this item separately as a task (pre-filled, tied to the deal). --}}
                                    <a href="{{ route('tasks.create', array_filter([
                                            'deal'        => $taskDeal,
                                            'title'       => \Illuminate\Support\Str::limit("SEO: {$r['label']} – {$taskSite}", 250, ''),
                                            'description' => \Illuminate\Support\Str::limit(trim(implode("\n", array_filter([
                                                $r['note'] ?? null,
                                                ($r['value'] ?? '') !== '' ? 'Leitud: ' . \Illuminate\Support\Str::limit((string) $r['value'], 300) : null,
                                                'Leht: ' . $audit->url,
                                                'Audit: ' . route('seo.audits.show', $audit),
                                            ]))), 1500),
                                        ])) }}"
                                       class="text-xs {{ $r['status'] === 'fail' ? 'text-indigo-600 hover:text-indigo-800 font-medium' : 'text-gray-400 hover:text-indigo-600' }}"
                                       title="Lisa see rida eraldi ülesandeks">+ ülesanne</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($audit->extras['categories'] ?? null)
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="font-semibold">E-poe kategooriad vs Google'i otsingud</h3>
                        <p class="text-xs text-gray-500">Otsingusoovitused = mida inimesed Google'isse trükivad (Eesti, eesti keel).</p>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase text-left">
                            <tr><th class="px-4 py-2 w-20"></th><th class="px-4 py-2">Kategooria</th><th class="px-4 py-2">Parem nimi</th><th class="px-4 py-2">Google'i soovitused</th></tr>
                        </thead>
                        <tbody>
                        @foreach($audit->extras['categories'] as $c)
                            <tr class="border-t border-gray-100 align-top">
                                <td class="px-4 py-2">
                                    @if($c['ok'] === true)<span class="text-green-700 bg-green-50 px-2 py-0.5 rounded text-xs">OK</span>
                                    @elseif($c['ok'] === false)<span class="text-red-700 bg-red-50 px-2 py-0.5 rounded text-xs">Muuta</span>
                                    @else<span class="text-gray-500 bg-gray-100 px-2 py-0.5 rounded text-xs">?</span>@endif
                                </td>
                                <td class="px-4 py-2"><a href="{{ $c['url'] }}" target="_blank" rel="noopener" class="text-indigo-600">{{ $c['name'] }}</a>
                                    @if($c['reason'] ?? null)<div class="text-xs text-gray-500">{{ $c['reason'] }}</div>@endif</td>
                                <td class="px-4 py-2 font-medium">{{ $c['better'] ?? '' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ implode(' · ', $c['suggestions'] ?? []) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
