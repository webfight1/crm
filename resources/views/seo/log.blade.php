<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Logi: {{ $lead->company ?: $lead->email }}</h2>
            <a href="{{ route('seo.clients') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← SEO kliendid</a>
        </div>
    </x-slot>

    @php
        $filters = [
            ''      => 'Kõik',
            'work'  => 'Minu töö',
            'mail'  => 'Kirjad',
            'stage' => 'Etapid',
        ];
        $groups = [
            'work'  => ['note', 'time', 'done', 'task', 'monitor'],
            'mail'  => ['mail_in', 'mail_out'],
            'stage' => ['stage', 'deal', 'quote', 'audit', 'fail'],
        ];
        $f = request('f', '');
        $shown = $f && isset($groups[$f]) ? $entries->whereIn('type', $groups[$f]) : $entries;
    @endphp

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4 text-sm flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="text-gray-500">„{{ $lead->serp_keyword }}“</span>
                @if($audit)<a href="{{ route('seo.audits.show', $audit) }}" class="text-indigo-600 hover:text-indigo-900">audit</a>@endif
                @if($deal)<a href="{{ route('deals.show', $deal) }}" class="text-indigo-600 hover:text-indigo-900">tehing</a>@endif
                <a href="{{ \App\Outreach\Models\OutreachMessage::inboxThreadUrl($lead->email) }}" class="text-indigo-600 hover:text-indigo-900">postkast</a>
                <span class="ml-auto text-gray-500">{{ $entries->count() }} kirjet</span>
            </div>

            <div x-data="{ open: false }" class="bg-white shadow-sm rounded-lg p-4">
                <button type="button" @click="open = ! open" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">📝 Lisa märge (mida tegin)</button>
                <form x-show="open" x-cloak method="POST" action="{{ route('seo.clients.log.note', $lead) }}" class="mt-3 space-y-2">
                    @csrf
                    <input name="title" required maxlength="480" placeholder="Nt „Uuendasin avalehe title ja meta kirjelduse“"
                           class="w-full rounded-md border-gray-300 text-sm">
                    <textarea name="body" rows="3" placeholder="Täpsem kirjeldus (valikuline)" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    <div class="flex items-center gap-3">
                        <label class="text-xs text-gray-500">Millal
                            <input type="datetime-local" name="at" value="{{ now()->format('Y-m-d\TH:i') }}" class="ml-1 rounded-md border-gray-300 text-sm">
                        </label>
                        <button class="ml-auto px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Salvesta</button>
                    </div>
                </form>
            </div>

            <div class="flex flex-wrap gap-2 text-sm">
                @foreach($filters as $key => $label)
                    <a href="{{ route('seo.clients.log', ['lead' => $lead, 'f' => $key ?: null]) }}"
                       class="px-3 py-1.5 rounded-full border {{ $f === $key ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">
                @forelse($shown->groupBy(fn ($e) => $e['at']->format('Y-m-d')) as $day => $items)
                    <div class="px-4 py-3">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ \Illuminate\Support\Carbon::parse($day)->format('d.m.Y') }}</div>
                        <ul class="space-y-2">
                            @foreach($items as $e)
                                <li class="flex gap-3 text-sm">
                                    <span class="w-12 shrink-0 text-xs text-gray-400 pt-0.5">{{ $e['at']->format('H:i') }}</span>
                                    <span class="shrink-0">{{ $e['icon'] }}</span>
                                    <div class="min-w-0">
                                        @if($e['url'])
                                            <a href="{{ $e['url'] }}" class="text-gray-900 hover:text-indigo-700">{{ $e['title'] }}</a>
                                        @else
                                            <span class="text-gray-900">{{ $e['title'] }}</span>
                                        @endif
                                        @if($e['who'])<span class="text-xs text-gray-400"> · {{ $e['who'] }}</span>@endif
                                        @if($e['body'])
                                            <div class="text-xs text-gray-500 whitespace-pre-line break-words">{{ $e['body'] }}</div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-500">Kirjeid pole.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
