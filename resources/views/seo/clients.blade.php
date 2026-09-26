<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SEO kliendid</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('seo.audits.index', ['warm' => 1]) }}#warm" class="text-indigo-600 hover:text-indigo-900 font-medium">+ Soe klient</a>
                <a href="{{ route('seo.playbook') }}" class="text-indigo-600 hover:text-indigo-900">Playbook →</a>
            </div>
        </div>
    </x-slot>

    @php
        $cell = [
            'done'   => 'bg-green-50 border-green-200 text-green-800',
            'me'     => 'bg-amber-50 border-amber-300 text-amber-900 font-medium',
            'client' => 'bg-sky-50 border-sky-200 text-sky-800',
            'fail'   => 'bg-red-50 border-red-200 text-red-700',
            'todo'   => 'bg-gray-50 border-gray-100 text-gray-300',
        ];
        $icon = ['done' => '✓', 'me' => '●', 'client' => '⏳', 'fail' => '✗', 'todo' => '○'];
        $tabs = [
            'open'   => 'Käimas',
            'me'     => 'Ootab minu järel (' . $counts['me'] . ')',
            'client' => 'Ootab klienti (' . $counts['client'] . ')',
            'done'   => 'Arveldatud (' . $counts['done'] . ')',
            'closed' => 'Kaotatud (' . $counts['closed'] . ')',
            'all'    => 'Kõik',
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('seo.clients', ['f' => $key]) }}"
                       class="px-3 py-1.5 rounded-full border {{ $filter === $key ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
                <span class="ml-auto flex flex-wrap gap-3 text-xs text-gray-500">
                    <span><span class="text-green-700">✓</span> tehtud</span>
                    <span><span class="text-amber-700">●</span> minu järel</span>
                    <span>⏳ ootab klienti</span>
                    <span class="text-gray-400">○ ees ootamas</span>
                </span>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase text-left">
                        <tr>
                            <th class="px-4 py-2">Klient</th>
                            @foreach($stages as $label)<th class="px-2 py-2 whitespace-nowrap">{{ $label }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr class="border-t border-gray-100 align-top {{ $row['closed'] ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2 min-w-[12rem]">
                                <a href="{{ route('deals.show', $row['deal']) }}" class="font-medium text-gray-900 hover:text-indigo-700">{{ $row['lead']->company ?: $row['lead']->email }}</a>
                                <div class="text-xs text-gray-500">„{{ $row['lead']->serp_keyword }}“@if($row['lead']->serp_position) · koht {{ $row['lead']->serp_position }}@endif</div>
                            </td>
                            @foreach($stages as $key => $label)
                                @php $s = $row['stages'][$key]; @endphp
                                <td class="px-1 py-2">
                                    @php $tag = $s['url'] && $s['state'] !== 'todo' ? 'a' : 'div'; @endphp
                                    <{{ $tag }} @if($tag === 'a') href="{{ $s['url'] }}" @if(! str_starts_with($s['url'], url('/'))) target="_blank" rel="noopener" @endif @endif
                                        class="block min-w-[6.5rem] rounded border px-2 py-1 text-xs leading-tight {{ $cell[$s['state']] }} {{ $tag === 'a' ? 'hover:ring-1 hover:ring-indigo-300' : '' }}">
                                        <span>{{ $icon[$s['state']] }}</span> {{ $s['text'] }}
                                        @if($s['days'] !== null && $s['days'] > 0)
                                            <span class="block text-[11px] {{ $s['days'] >= 3 ? 'text-red-600 font-semibold' : 'opacity-70' }}">{{ $s['days'] }} p</span>
                                        @endif
                                    </{{ $tag }}>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($stages) + 1 }}" class="px-4 py-8 text-center text-gray-500">Siin pole kedagi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500">Iga lahter on link sinna, kus seda sammu tehakse. Päevad = kui kaua samm juba ootab (punane alates 3 päevast).</p>
        </div>
    </div>
</x-app-layout>
