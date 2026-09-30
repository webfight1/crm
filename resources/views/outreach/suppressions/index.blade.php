<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Loobujad</h2>
            <a href="{{ route('outreach.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Kampaaniad →</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(isset($errors) && $errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-5 text-sm text-gray-600">
                Siin olevatele aadressidele ei saadeta ühestki kampaaniast kirju ja CSV import jätab need vahele.
                Nimekirja lisatakse automaatselt, kui lead <strong>loobub</strong>, kiri <strong>tagastub</strong>
                või AI liigitab vastuse <strong>„pole huvitatud”</strong>. Domeen (nt <code>foo.ee</code>) blokeerib kõik selle domeeni aadressid.
            </div>

            <form method="POST" action="{{ route('outreach.suppressions.store') }}" enctype="multipart/form-data"
                  class="bg-white shadow-sm rounded-lg p-5 space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emailid või domeenid (üks rea kohta)</label>
                        <textarea name="values" rows="4" class="w-full border-gray-300 rounded text-sm"
                                  placeholder="info@firma.ee&#10;teinefirma.ee"></textarea>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">…või CSV fail</label>
                            <input type="file" name="file" accept=".csv,.txt,text/csv" class="block w-full text-sm">
                            <p class="text-xs text-gray-400 mt-1">Failist võetakse kõik emailiaadressid, veerust sõltumata.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Märkus (valikuline)</label>
                            <input type="text" name="note" class="w-full border-gray-300 rounded text-sm" placeholder="nt palus telefonis mitte kirjutada">
                        </div>
                    </div>
                </div>
                <x-primary-button>Lisa loobujatesse</x-primary-button>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <form method="GET" class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Otsi emaili või domeeni" class="border-gray-300 rounded text-sm">
                    <select name="reason" class="border-gray-300 rounded text-sm" onchange="this.form.submit()">
                        <option value="">Kõik põhjused ({{ $counts->sum() }})</option>
                        @foreach(\App\Outreach\Models\OutreachSuppression::REASONS as $key => $label)
                            <option value="{{ $key }}" @selected(request('reason') === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                    <button class="px-3 py-2 text-sm rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Otsi</button>
                </form>

                @if($entries->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">Nimekiri on tühi.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                                <tr>
                                    <th class="px-4 py-2 text-left">Email / domeen</th>
                                    <th class="px-4 py-2 text-left">Põhjus</th>
                                    <th class="px-4 py-2 text-left">Kampaania</th>
                                    <th class="px-4 py-2 text-left">Märkus</th>
                                    <th class="px-4 py-2 text-left">Lisatud</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($entries as $e)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-900">
                                            {{ $e->value }}
                                            @if($e->type === 'domain')<span class="ml-1 text-xs text-gray-400">(domeen)</span>@endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ \App\Outreach\Models\OutreachSuppression::REASONS[$e->reason] ?? $e->reason }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $e->lead?->campaign?->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $e->note ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-400 whitespace-nowrap">{{ $e->created_at?->format('d.m.Y') }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <form method="POST" action="{{ route('outreach.suppressions.destroy', $e) }}"
                                                  onsubmit="return confirm('Eemaldan {{ $e->value }} loobujate nimekirjast?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:text-red-800">Eemalda</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-3">{{ $entries->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
