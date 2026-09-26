<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SEO Playbook</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('seo.clients') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Kliendid</a>
                <a href="{{ route('seo.docs') }}" class="text-indigo-600 hover:text-indigo-900">Protsessi kirjeldus</a>
                <a href="{{ route('seo.audits.index', ['warm' => 1]) }}#warm" class="text-indigo-600 hover:text-indigo-900 font-medium">+ Soe klient</a>
                <a href="{{ route('seo.audits.index') }}" class="text-indigo-600 hover:text-indigo-900">Auditid →</a>
                <a href="{{ route('outreach.campaigns.index') }}" class="text-indigo-600 hover:text-indigo-900">Kampaaniad →</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Pipeline overview --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-1">Protsess</h3>
                <p class="text-sm text-gray-500 mb-4">Kõik reeglid on sellel lehel. Muudatused kehtivad kohe, koodi pole vaja muuta. Protsessi loogika ja arendusplaan: <a href="{{ route('seo.docs') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Protsessi kirjeldus →</a></p>
                <ol class="grid grid-cols-2 md:grid-cols-6 gap-2 text-sm">
                    @foreach([
                        ['1. CSV import', 'Filter + veerunimed'],
                        ['2. Müügikiri', 'AI juhised + kampaania'],
                        ['3. Vastus', 'AI liigitab'],
                        ['4. Soe klient', 'Klient + tehing'],
                        ['5. Audit', 'Kontrollnimekiri'],
                        ['6. Pakkumine', 'Mustand, sina saadad'],
                    ] as [$step, $what])
                        <li class="border border-gray-200 rounded p-3">
                            <div class="font-medium text-gray-900">{{ $step }}</div>
                            <div class="text-xs text-gray-500">{{ $what }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Stats --}}
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Lehter</h3>
                    <dl class="space-y-1 text-sm">
                        @foreach($funnel as $row)
                            <div class="flex justify-between"><dt class="text-gray-600">{{ $row['label'] }}</dt><dd class="font-semibold tabular-nums">{{ $row['count'] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold mb-1">Vastamismäär positsiooni järgi</h3>
                    <p class="text-xs text-gray-500 mb-3">Näitab, millist positsioonivahemikku filtris sihtida.</p>
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-gray-500 text-xs"><th>Koht</th><th class="text-right">Saadetud</th><th class="text-right">Vastas</th><th class="text-right">%</th></tr></thead>
                        <tbody>
                        @foreach($buckets as $b)
                            <tr class="border-t border-gray-100">
                                <td class="py-1">{{ $b['label'] }}</td>
                                <td class="text-right tabular-nums">{{ $b['sent'] }}</td>
                                <td class="text-right tabular-nums">{{ $b['replied'] }}</td>
                                <td class="text-right tabular-nums font-semibold">{{ $b['rate'] === null ? '—' : $b['rate'] . '%' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Vastuste liigid</h3>
                    <dl class="space-y-1 text-sm">
                        @foreach($intents as $i)
                            <div class="flex justify-between"><dt class="text-gray-600">{{ $i['label'] }}</dt><dd class="font-semibold tabular-nums">{{ $i['count'] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            </div>

            {{-- Settings --}}
            <form method="POST" action="{{ route('seo.playbook.update') }}" class="space-y-6">
                @csrf @method('PATCH')

                @foreach($sections as $sectionKey => $sectionLabel)
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">{{ $sectionLabel }}</h3>
                        <div class="grid md:grid-cols-2 gap-x-6 gap-y-5">
                            @foreach($settings[$sectionKey] ?? [] as $key => $s)
                                @php $field = str_replace('.', '__', $key); @endphp
                                <div class="{{ in_array($s['type'], ['textarea', 'lines']) ? 'md:col-span-2' : '' }}">
                                    @if($s['type'] === 'bool')
                                        <label class="inline-flex items-start gap-2">
                                            <input type="checkbox" name="{{ $field }}" value="1" @checked($s['value'] === '1')
                                                   class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span>
                                                <span class="font-medium text-sm text-gray-700">{{ $s['label'] }}</span>
                                                @if($s['help'])<span class="block text-xs text-gray-500">{{ $s['help'] }}</span>@endif
                                            </span>
                                        </label>
                                    @else
                                        <x-input-label :for="$field" :value="$s['label']" />
                                        @if(in_array($s['type'], ['textarea', 'lines']))
                                            <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ max(3, min(10, substr_count($s['value'], "\n") + 2)) }}"
                                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">{{ $s['value'] }}</textarea>
                                        @else
                                            <x-text-input :id="$field" :name="$field" :type="$s['type'] === 'int' ? 'number' : 'text'" :value="$s['value']" class="mt-1 block w-full" />
                                        @endif
                                        @if($s['help'])<p class="mt-1 text-xs text-gray-500">{{ $s['help'] }}</p>@endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <x-primary-button>Salvesta Playbook</x-primary-button>
                </div>
            </form>

            {{-- Audit checklist --}}
            <div id="checks" class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Auditi kontrollnimekiri</h3>
                    <p class="text-sm text-gray-500">Sisseehitatud kontrollid mõõdab kood. <strong>AI-kontrolli</strong> kirjutad ise tavakeeles küsimusena ja AI vastab lehe sisu põhjal. Kui kontroll kukub läbi ja sellel on hinnaga parandus, läheb parandus pakkumisse eraldi reana. <strong>Kehtib</strong> määrab, kas kontroll tehakse kõigile, ainult kodulehtedele või ainult e-poodidele (audit tuvastab saidi tüübi ise).</p>
                </div>

                @foreach($checks as $c)
                    <details class="border-b border-gray-100">
                        <summary class="cursor-pointer px-6 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm">
                            <span class="inline-block w-2 h-2 rounded-full {{ $c->enabled ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                            <span class="flex-1 {{ $c->enabled ? 'text-gray-900' : 'text-gray-400' }}">{{ $c->label }}</span>
                            @if($c->applies_to !== 'all')
                                <span class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800">{{ $c->applies_to === 'eshop' ? 'e-pood' : 'koduleht' }}</span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded {{ $c->isBuiltin() ? 'bg-gray-100 text-gray-600' : 'bg-purple-100 text-purple-700' }}">{{ $c->isBuiltin() ? 'sisseehitatud' : 'AI' }}</span>
                            <span class="text-xs text-gray-500 w-16 text-right">kaal {{ $c->weight }}</span>
                            <span class="text-xs text-gray-700 w-24 text-right tabular-nums">{{ $c->fix_price ? number_format((float) $c->fix_price, 0, ',', ' ') . ' €' : '—' }}</span>
                        </summary>
                        <div class="px-6 pb-5 pt-2 bg-gray-50">
                            @include('seo._check_form', ['check' => $c, 'action' => route('seo.checks.update', $c), 'method' => 'PATCH'])
                            @unless($c->isBuiltin())
                                <form method="POST" action="{{ route('seo.checks.destroy', $c) }}" class="mt-2 text-right" onsubmit="return confirm('Kustutada see kontroll?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:text-red-800">Kustuta</button>
                                </form>
                            @endunless
                        </div>
                    </details>
                @endforeach

                <datalist id="seo-fix-groups">
                    @foreach($checks->pluck('fix_group')->filter()->unique() as $g)<option value="{{ $g }}">@endforeach
                </datalist>

                <div class="px-6 py-5">
                    <h4 class="font-semibold mb-3">+ Uus AI-kontroll</h4>
                    @include('seo._check_form', ['check' => null, 'action' => route('seo.checks.store'), 'method' => 'POST'])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
