<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SEO auditid</h2>
            <a href="{{ route('seo.playbook') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Playbook</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold mb-3">Auditeeri leht käsitsi</h3>
                <form method="POST" action="{{ route('seo.audits.store') }}" class="grid md:grid-cols-5 gap-3">
                    @csrf
                    <div class="md:col-span-2">
                        <x-input-label value="Leht" />
                        <x-text-input name="url" required class="mt-1 block w-full" placeholder="naide.ee" />
                        <x-input-error :messages="$errors->get('url')" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label value="Märksõna (valikuline)" />
                        <x-text-input name="keyword" class="mt-1 block w-full" placeholder="nt: elektritööd tartus" />
                    </div>
                    <div class="flex items-end">
                        <x-primary-button class="w-full justify-center">Käivita</x-primary-button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-gray-500">SEO-leadidele, kes vastavad huviga, käivitub audit automaatselt (Playbook → Automaatika).</p>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase text-left">
                        <tr>
                            <th class="px-4 py-2">Leht</th>
                            <th class="px-4 py-2">Märksõna</th>
                            <th class="px-4 py-2 text-right">Skoor</th>
                            <th class="px-4 py-2">Tehing</th>
                            <th class="px-4 py-2">Pakkumine</th>
                            <th class="px-4 py-2">Aeg</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($audits as $a)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ route('seo.audits.show', $a) }}" class="text-indigo-600 hover:text-indigo-900">{{ $a->lead?->company ?: parse_url($a->url, PHP_URL_HOST) ?: $a->url }}</a>
                                @if($a->status !== 'done')
                                    <span class="ml-1 text-xs {{ $a->status === 'failed' ? 'text-red-600' : 'text-yellow-600' }}">{{ $a->status === 'failed' ? 'ebaõnnestus' : 'töös…' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $a->keyword ?: '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $a->score ?? '—' }}</td>
                            <td class="px-4 py-2">@if($a->deal)<a href="{{ route('deals.show', $a->deal) }}" class="text-indigo-600">{{ \Illuminate\Support\Str::limit($a->deal->title, 30) }}</a>@else — @endif</td>
                            <td class="px-4 py-2">@if($a->quotation)<a href="{{ route('quotations.show', $a->quotation) }}" class="text-indigo-600">{{ $a->quotation->number }}</a> <span class="text-xs text-gray-500">{{ $a->quotation->status }}</span>@else — @endif</td>
                            <td class="px-4 py-2 text-gray-500">{{ $a->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Auditeid veel pole.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $audits->links() }}
        </div>
    </div>
</x-app-layout>
