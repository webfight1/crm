<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Uus kampaania</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('outreach.campaigns.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nimi" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Kirjeldus (valikuline)" />
                        <textarea id="description" name="description" rows="2"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="daily_limit" value="Kampaania päevalimiit (tühi = piiramatu)" />
                        <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" :value="old('daily_limit')" placeholder="nt 100" />
                        <p class="text-xs text-gray-500 mt-1">Lisapiirang üle kõigi postkastide kokku.</p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="reply_stop_enabled" value="1" @checked(old('reply_stop_enabled', true)) class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Peata saatmine vastuse korral</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="use_ai_line" value="1" @checked(old('use_ai_line', false)) class="rounded border-gray-300 text-indigo-600 mt-0.5">
                            <span class="text-sm text-gray-700">
                                Kasuta AI isikupärastamist
                                <span class="block text-xs text-gray-400 font-normal">Genereerib iga leadi jaoks ühe lause kasutades OpenAI-t. Kasuta <code class="bg-gray-100 px-1 rounded">&#123;&#123;ai_line&#125;&#125;</code> meilimallides.</span>
                            </span>
                        </label>
                    </div>

                    {{-- AI prompt (shown only when use_ai_line is relevant) --}}
                    <div>
                        <x-input-label for="ai_prompt" value="AI prompt (valikuline)" />
                        <textarea id="ai_prompt" name="ai_prompt" rows="5"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                            placeholder="Kirjuta üks lühike lause külmale meilile.&#10;&#10;Ettevõte: &#123;&#123;company&#125;&#125;&#10;Veebileht: &#123;&#123;website&#125;&#125;&#10;Tööstus: &#123;&#123;industry&#125;&#125;&#10;&#10;Reeglid:&#10;- Max 15 sõna&#10;- Vabas toonis, eesti keeles&#10;- Ära kasuta emotikonide ega hüüumärke">{{ old('ai_prompt') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Jäta tühjaks vaikimisi promti kasutamiseks. Saadaval muutujad:
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;company&#125;&#125;</code>
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;website&#125;&#125;</code>
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;industry&#125;&#125;</code>
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;first_name&#125;&#125;</code>
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;last_name&#125;&#125;</code>
                            <code class="bg-gray-100 px-1 rounded">&#123;&#123;email&#125;&#125;</code>
                        </p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Aktiivne</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <x-primary-button>Loo kampaania</x-primary-button>
                        <a href="{{ route('outreach.campaigns.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Tühista</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
