<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">WhatsAppi ühendus</h2>
            <a href="{{ route('chats.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Vestlused</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @include('chats._flash')

            <div class="bg-white shadow-sm rounded-lg p-6">
                @if(! $enabled)
                    <p class="text-sm text-gray-700">WuzAPI pole seadistatud. Lisa <code>.env</code>-i <code>WUZAPI_USER_TOKEN</code> ja <code>WUZAPI_WEBHOOK_SECRET</code> (vt <code>docker/wuzapi/README.md</code>).</p>
                @else
                    <div x-data="waConnect('{{ route('chats.connect.status') }}')" x-init="poll()" class="space-y-4">
                        <p class="text-sm">
                            Olek:
                            <span x-show="!state" class="text-gray-500">kontrollin…</span>
                            <span x-show="state && !state.reachable" class="text-red-600 font-medium">WuzAPI ei vasta</span>
                            <span x-show="state && state.reachable && state.loggedIn" class="text-green-700 font-medium">✓ Ühendatud — sõnumid jõuavad CRM-i</span>
                            <span x-show="state && state.reachable && !state.loggedIn" class="text-amber-700 font-medium">Pole sisse logitud</span>
                        </p>

                        <template x-if="state && state.qr">
                            <div>
                                <p class="text-sm text-gray-700 mb-2">Telefonis: WhatsApp → Seaded → <b>Lingitud seadmed</b> → Lingi seade, ja skaneeri:</p>
                                <img :src="state.qr" alt="QR" class="w-64 h-64 border rounded">
                            </div>
                        </template>

                        <div class="flex gap-2" x-show="state && state.reachable">
                            <form method="POST" action="{{ route('chats.connect.start') }}" x-show="!state?.loggedIn">
                                @csrf
                                <button class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700">Ühenda / näita QR-koodi</button>
                            </form>
                            <form method="POST" action="{{ route('chats.connect.logout') }}" x-show="state?.loggedIn"
                                  onsubmit="return confirm('Ühendada WhatsApp CRM-ist lahti?')">
                                @csrf
                                <button class="px-3 py-1.5 bg-gray-200 text-gray-800 text-sm rounded hover:bg-gray-300">Ühenda lahti</button>
                            </form>
                        </div>

                        <p class="text-xs text-gray-500">CRM ainult loeb sõnumeid. Salvestatakse ainult jälgitavate (kliendi) vestluste sisu; teistest ainult nimi ja viimase sõnumi aeg.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function waConnect(url) {
            return {
                state: null,
                async poll() {
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        this.state = await r.json();
                    } catch (e) {
                        this.state = { reachable: false };
                    }
                    // QR codes rotate every ~20 s; stop polling once logged in.
                    if (!this.state.loggedIn) setTimeout(() => this.poll(), 4000);
                },
            };
        }
    </script>
</x-app-layout>
