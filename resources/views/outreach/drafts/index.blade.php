<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🤖 AI mustandid — {{ $campaign->name }}
            </h2>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('outreach.campaigns.leads.index', $campaign) }}" class="text-indigo-600 hover:text-indigo-900">← Leadid</a>
                <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-900">Kampaania</a>
            </div>
        </div>
    </x-slot>

    @if(($counts['pending'] ?? 0) > 0)
        @push('scripts')
            {{-- Auto-refresh only while queued generations are still running,
                 so an idle drafts page doesn't reload every 10s forever. --}}
            <script>setTimeout(() => location.reload(), 10000);</script>
        @endpush
    @endif

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Batch generator + status summary --}}
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium">{{ $counts['total'] }}</span> leadi kokku ·
                        <span class="text-emerald-700">{{ $counts['approved'] }}</span> kinnitatud ·
                        <span class="text-amber-700">{{ $counts['ready'] }}</span> ootel ülevaatust ·
                        @if(($counts['pending'] ?? 0) > 0)
                            <span class="text-blue-700">{{ $counts['pending'] }}</span> järjekorras ·
                        @endif
                        <span class="text-red-700">{{ $counts['failed'] }}</span> ebaõnnestunud ·
                        <span class="text-gray-500">{{ $counts['missing'] }}</span> genereerimata
                    </div>
                    <form method="POST" action="{{ route('outreach.campaigns.drafts.generate-batch', $campaign) }}"
                          class="flex items-center gap-2">
                        @csrf
                        <label class="text-sm text-gray-600">Genereeri</label>
                        <select name="limit" class="text-sm border-gray-300 rounded">
                            @foreach([10, 25, 50] as $n)
                                <option value="{{ $n }}">{{ $n }} tk</option>
                            @endforeach
                        </select>
                        <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="force" value="1" class="rounded border-gray-300">
                            uuesti (kirjuta üle)
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">
                            🤖 Genereeri
                        </button>
                    </form>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Suuremad partiid (100+) käivita SSH-st:
                    <code class="bg-gray-100 px-1 rounded">php artisan outreach:generate-drafts {{ $campaign->id }} --limit=100</code>
                </p>
            </div>

            {{-- Filter chips --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['all'=>'Kõik','ready'=>'Ülevaatust ootavad','approved'=>'Kinnitatud','failed'=>'Ebaõnnestunud','missing'=>'Genereerimata'] as $k => $label)
                    @php
                        $active = $filter === $k;
                        $url    = route('outreach.campaigns.drafts.index', ['campaign' => $campaign, 'filter' => $k]);
                    @endphp
                    <a href="{{ $url }}"
                       class="px-3 py-1.5 rounded-full text-xs {{ $active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Bulk approve form wrapper --}}
            <form method="POST" action="{{ route('outreach.campaigns.drafts.approve-batch', $campaign) }}">
                @csrf
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-700 inline-flex items-center gap-2">
                            <input type="checkbox" onclick="document.querySelectorAll('.draft-cb').forEach(c=>c.checked=this.checked)"
                                   class="rounded border-gray-300">
                            Vali kõik nähtavad
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded">
                            ✓ Kinnita valitud saatmiseks
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-4 py-3">Ettevõte / e-post</th>
                                <th class="px-4 py-3">Staatus</th>
                                <th class="px-4 py-3">Subjekt (valitud)</th>
                                <th class="px-4 py-3">Kirja algus</th>
                                <th class="px-4 py-3 text-right">Tegevus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($leads as $lead)
                                @php
                                    $subjIdx = $lead->outreach_selected_subject ?? 1;
                                    $subject = $lead->{"outreach_subject_$subjIdx"} ?? '—';
                                    $bodyPreview = \Illuminate\Support\Str::limit(strip_tags($lead->outreach_email_body ?? ''), 100);
                                    $badge = match($lead->outreach_generation_status) {
                                        'ready'    => ['Ülevaatuseks','bg-amber-100 text-amber-800'],
                                        'approved' => ['Kinnitatud','bg-emerald-100 text-emerald-800'],
                                        'failed'   => ['Vigane','bg-red-100 text-red-800'],
                                        'pending'  => ['Töös','bg-blue-100 text-blue-800'],
                                        default    => ['Genereerimata','bg-gray-100 text-gray-600'],
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        @if($lead->outreach_generation_status === 'ready')
                                            <input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}"
                                                   class="draft-cb rounded border-gray-300">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $lead->company ?: '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ $lead->email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs {{ $badge[1] }}">{{ $badge[0] }}</span>
                                        @if($lead->outreach_generation_error)
                                            <p class="text-[10px] text-red-600 mt-1 font-mono truncate max-w-[16rem]" title="{{ $lead->outreach_generation_error }}">
                                                {{ \Illuminate\Support\Str::limit($lead->outreach_generation_error, 60) }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $subject }}">
                                        {{ $subject }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-md truncate" title="{{ strip_tags($lead->outreach_email_body ?? '') }}">
                                        {{ $bodyPreview ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('outreach.leads.draft.show', $lead) }}"
                                           class="text-indigo-600 hover:text-indigo-900 text-xs">👁 Vaata / Muuda</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">
                                        Selle filtriga leadi pole. Käivita batch üleval, et genereerida.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>

                    @if($leads->hasPages())
                        <div class="px-5 py-3 border-t border-gray-200">{{ $leads->links() }}</div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
