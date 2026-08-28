<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🤖 Mustand — {{ $lead->company ?: $lead->email }}
            </h2>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('outreach.campaigns.drafts.index', $lead->campaign_id) }}" class="text-indigo-600 hover:text-indigo-900">← Kõik mustandid</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Lead metadata --}}
            <div class="bg-white shadow-sm rounded-lg p-5 grid md:grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="text-xs text-gray-500 uppercase">Klient</div>
                    <div class="font-medium">{{ $lead->first_name }} {{ $lead->last_name }}</div>
                    <div class="text-gray-600">{{ $lead->email }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Ettevõte</div>
                    <div class="font-medium">{{ $lead->company ?: '—' }}</div>
                    <div class="text-gray-600">{{ $lead->industry ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Veeb</div>
                    @if($lead->website)
                        <a href="{{ $lead->website }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline break-all">{{ $lead->website }}</a>
                    @else <span class="text-gray-400">—</span> @endif
                    @if($lead->design_year)
                        <div class="text-xs text-gray-500 mt-1">Disain: ~{{ $lead->design_year }} ({{ $lead->design_age }} a.)</div>
                    @endif
                </div>
            </div>

            {{-- Status + regenerate --}}
            <div class="flex items-center justify-between bg-white shadow-sm rounded-lg px-5 py-3">
                <div class="flex items-center gap-2">
                    @php
                        $st = $lead->outreach_generation_status;
                        $badge = match($st) {
                            'ready'    => ['🟡 Ülevaatuseks','bg-amber-100 text-amber-800'],
                            'approved' => ['🟢 Kinnitatud saatmiseks','bg-emerald-100 text-emerald-800'],
                            'failed'   => ['🔴 Ebaõnnestunud','bg-red-100 text-red-800'],
                            'pending'  => ['⏳ Töös','bg-blue-100 text-blue-800'],
                            default    => ['⚪ Genereerimata','bg-gray-100 text-gray-600'],
                        };
                    @endphp
                    <span class="px-2 py-1 rounded text-xs {{ $badge[1] }}">{{ $badge[0] }}</span>
                    @if($lead->outreach_generated_at)
                        <span class="text-xs text-gray-500">Genereeritud {{ $lead->outreach_generated_at->diffForHumans() }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($prevId)
                        <a href="{{ route('outreach.leads.draft.show', $prevId) }}" class="text-xs text-gray-500 hover:text-gray-800">← Eelmine (J)</a>
                    @endif
                    @if($nextId)
                        <a href="{{ route('outreach.leads.draft.show', $nextId) }}" class="text-xs text-gray-500 hover:text-gray-800">Järgmine (K) →</a>
                    @endif
                    <form method="POST" action="{{ route('outreach.leads.draft.generate', $lead) }}" class="inline">
                        @csrf
                        <button class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-800 text-xs rounded">🔄 Regenereeri (R)</button>
                    </form>
                    @if($st === 'approved')
                        <form method="POST" action="{{ route('outreach.leads.draft.unapprove', $lead) }}" class="inline">
                            @csrf
                            <button class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs rounded">↩ Tühista kinnitus</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($st === 'failed')
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">
                    <div class="font-medium">Genereerimine ebaõnnestus:</div>
                    <div class="font-mono text-xs mt-1">{{ $lead->outreach_generation_error }}</div>
                </div>
            @endif

            {{-- AI-generated context observations --}}
            @if($lead->website_context_summary || $lead->public_reference_context || $lead->seo_observation)
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm space-y-2">
                    <div class="text-xs font-medium text-gray-700 uppercase mb-1">AI kontekst</div>
                    @if($lead->website_context_summary)
                        <div><span class="text-gray-500">Kodulehelt:</span> {{ $lead->website_context_summary }}</div>
                    @endif
                    @if($lead->public_reference_context)
                        <div><span class="text-gray-500">Referentsid:</span> {{ $lead->public_reference_context }}</div>
                    @endif
                    @if($lead->seo_observation)
                        <div><span class="text-gray-500">SEO tähelepanek:</span> {{ $lead->seo_observation }}</div>
                    @endif
                    @if($lead->outreach_sources)
                        <div class="text-xs text-gray-400 mt-2">
                            Allikad:
                            @foreach($lead->outreach_sources as $src)
                                <a href="{{ $src }}" target="_blank" rel="noopener" class="hover:underline">{{ parse_url($src, PHP_URL_HOST) }}</a>@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Editor form --}}
            <form method="POST" action="{{ route('outreach.leads.draft.update', $lead) }}" class="bg-white shadow-sm rounded-lg p-5 space-y-5">
                @csrf @method('PATCH')

                {{-- Subject picker: three variants as radio pills --}}
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-2">Vali subjekt</div>
                    <div class="space-y-2">
                        @foreach([1, 2, 3] as $i)
                            @php $field = "outreach_subject_$i"; @endphp
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="radio" name="outreach_selected_subject" value="{{ $i }}"
                                       @checked(($lead->outreach_selected_subject ?? 1) == $i)
                                       class="mt-2 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                <input type="text" name="{{ $field }}" value="{{ old($field, $lead->{$field}) }}"
                                       class="flex-1 text-sm border-gray-300 rounded" placeholder="Subjekt #{{ $i }}">
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label value="Kirja sisu (esimene kiri, step 1)" />
                    <textarea name="outreach_email_body" rows="16"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">{{ old('outreach_email_body', $lead->outreach_email_body) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Salvestatakse HTML-ina. Reavahetused säilivad. Postkasti signatuur + kampaania jaluse lisatakse automaatselt.</p>
                </div>

                <div>
                    <x-input-label value="Follow-up kiri (step 2)" />
                    <textarea name="outreach_followup_body" rows="8"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">{{ old('outreach_followup_body', $lead->outreach_followup_body) }}</textarea>
                </div>

                <div class="flex items-center justify-between border-t pt-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="approve" value="1" @checked($st === 'approved') class="rounded border-gray-300 text-emerald-600">
                        Kinnita saatmiseks
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">
                            Salvesta
                        </button>
                        @if($nextId)
                            <button type="submit" name="goto_next" value="1"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded">
                                Salvesta + Järgmine (A)
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Small keyboard shortcuts: J/K navigate, R regenerate, A save+next.
        // Ignored when the user is typing into an input/textarea.
        document.addEventListener('keydown', (e) => {
            const t = e.target;
            if (t && ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) return;
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            const map = {
                'j': @json($prevId ? route('outreach.leads.draft.show', $prevId) : null),
                'k': @json($nextId ? route('outreach.leads.draft.show', $nextId) : null),
            };
            if (map[e.key]) { window.location.href = map[e.key]; return; }
            if (e.key === 'a' || e.key === 'A') {
                const btn = document.querySelector('button[name="goto_next"]');
                if (btn) { document.querySelector('input[name="approve"]')?.setAttribute('checked','checked'); btn.click(); }
            }
            if (e.key === 'r' || e.key === 'R') {
                document.querySelector('form[action*="/draft/generate"] button')?.click();
            }
        });
    </script>
    @endpush
</x-app-layout>
