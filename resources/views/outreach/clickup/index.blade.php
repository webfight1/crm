<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">ClickUpi import</h2>
            <a href="{{ route('outreach.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Kampaaniad →</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            @if(!empty($error) || (isset($errors) && $errors->any()))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">
                    {{ $error ?? $errors->first() }}
                </div>
            @endif

            @unless($configured)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                    <strong>CLICKUP_API_TOKEN puudub .env failist.</strong>
                    Lisa see (ClickUp → Settings → Apps → API Token) ja jooksuta <code>php artisan config:clear</code>.
                </div>
            @endunless

            <form method="POST" class="bg-white shadow-sm rounded-lg p-5 space-y-4">
                @csrf

                {{-- Kiirvalik: salvestatud listid --}}
                @if(count($sources))
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-2">Salvestatud listid</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($sources as $s)
                                <button type="button"
                                        onclick="document.getElementById('clickup-source').value = @js($s['url'])"
                                        class="px-3 py-1.5 text-sm rounded border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                    {{ $s['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label for="clickup-source" class="block text-sm font-medium text-gray-700 mb-1">
                        ClickUpi listi või vaate URL
                    </label>
                    <input type="text" name="source" id="clickup-source" value="{{ $source }}"
                           placeholder="https://app.clickup.com/9015331367/v/l/li/901523799837"
                           class="w-full border-gray-300 rounded text-sm">
                    <p class="text-xs text-gray-400 mt-1">
                        Sobib nii listi URL, chat-vaate URL kui ka paljas id. Chat-vaate puhul võetakse selle ema-list.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="per_contact" value="1" class="rounded border-gray-300"
                               @checked($perContact ?? true)>
                        Rida iga kontakti kohta (Email, Email 2, Email 3)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="with_empty" value="1" class="rounded border-gray-300"
                               @checked($withEmpty ?? false)>
                        Näita ka emailita ridu
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <x-primary-button formaction="{{ route('outreach.clickup.preview') }}">
                        Vaata andmeid
                    </x-primary-button>

                    <button type="submit" formaction="{{ route('outreach.clickup.download') }}"
                            class="px-4 py-2 text-sm rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        Laadi CSV alla
                    </button>

                    @if($rows !== null && count($rows))
                        <span class="text-gray-300">|</span>
                        <select name="campaign_id" class="border-gray-300 rounded text-sm">
                            <option value="">— vali kampaania —</option>
                            @foreach($campaigns as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" formaction="{{ route('outreach.clickup.import') }}"
                                onclick="return confirm('Impordin {{ count($rows) }} rida valitud kampaaniasse. Jätkan?')"
                                class="px-4 py-2 text-sm rounded bg-green-600 text-white hover:bg-green-700">
                            Impordi kampaaniasse
                        </button>
                    @endif
                </div>
            </form>

            @if($rows !== null)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm text-gray-700">
                            <strong>{{ $taskCount ?? 0 }}</strong> taski →
                            <strong>{{ count($rows) }}</strong> rida
                        </p>
                        @if(count($rows) > 100)
                            <p class="text-xs text-gray-400">näidatud esimesed 100</p>
                        @endif
                    </div>

                    @if(count($rows) === 0)
                        <p class="px-5 py-6 text-sm text-gray-500">Ühtegi rida ei tulnud.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Firma</th>
                                        <th class="px-4 py-2 text-left">Kontakt</th>
                                        <th class="px-4 py-2 text-left">Email</th>
                                        <th class="px-4 py-2 text-left">Veeb</th>
                                        <th class="px-4 py-2 text-left">Staatus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach(array_slice($rows, 0, 100) as $r)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-900">{{ $r['company'] }}</td>
                                            <td class="px-4 py-2 text-gray-600">
                                                {{ trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—' }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-600">{{ $r['email'] ?: '—' }}</td>
                                            <td class="px-4 py-2 text-gray-400 truncate max-w-xs">{{ $r['website'] ?: '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $r['status'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
