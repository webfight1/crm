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
                        @if($audit->status === 'done' && ! $audit->quotation_id && $audit->deal_id)
                            <form method="POST" action="{{ route('seo.audits.offer', $audit) }}">@csrf
                                <x-primary-button class="w-full justify-center">Koosta pakkumine</x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            @if($audit->summary)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold mb-2">Kokkuvõte kliendile</h3>
                    <div class="text-sm text-gray-800 whitespace-pre-line">{{ $audit->summary }}</div>
                </div>
            @endif

            @if($audit->results)
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase text-left">
                            <tr><th class="px-4 py-2 w-24">Tulemus</th><th class="px-4 py-2">Kontroll</th><th class="px-4 py-2">Leitud</th><th class="px-4 py-2 text-right">Kaal</th></tr>
                        </thead>
                        <tbody>
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
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
