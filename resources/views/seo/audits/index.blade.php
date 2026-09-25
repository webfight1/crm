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

            <details id="warm" class="bg-white shadow-sm rounded-lg" @if($errors->any() || request()->has('warm')) open @endif>
                <summary class="cursor-pointer px-6 py-4 font-semibold">+ Lisa soe klient käsitsi</summary>
                <form method="POST" action="{{ route('seo.warm.store') }}" class="px-6 pb-6 grid md:grid-cols-3 gap-4 text-sm">
                    @csrf
                    <p class="md:col-span-3 text-gray-500">Klient, kes on huvitatud, aga ei tulnud outreachi kaudu (telefon, soovitus). Edasi käib kõik nagu vastanud leadiga: klient + tehing CRM-is, audit märksõna lehel, täpsustuskirja mustand postkastis ja pakkumise mustand pärast kliendi vastust.</p>
                        {{-- Company autocomplete: CRM companies + customers + business register.
                             Picking a result fills the empty contact fields below. --}}
                        <div class="relative" x-data="{
                                q: @js(old('company', '')), results: [], open: false, loading: false, timer: null,
                                search() {
                                    clearTimeout(this.timer);
                                    if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                                    this.timer = setTimeout(async () => {
                                        this.loading = true;
                                        try {
                                            const r = await fetch(@js(route('seo.companies.search')) + '?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                                            this.results = r.ok ? await r.json() : [];
                                        } catch (e) { this.results = []; }
                                        this.loading = false; this.open = true;
                                    }, 250);
                                },
                                pick(c) {
                                    this.q = c.company || this.q;
                                    this.$refs.regcode.value = c.registrikood || '';
                                    const fill = (id, v) => { const el = document.getElementById(id); if (el && v && !el.value) el.value = v; };
                                    fill('w_first_name', c.first_name); fill('w_last_name', c.last_name);
                                    fill('w_email', c.email); fill('w_website', c.website);
                                    this.open = false;
                                },
                                badge: { crm: ['CRM', 'bg-indigo-100 text-indigo-700'], customer: ['Klient', 'bg-green-100 text-green-700'], register: ['Äriregister', 'bg-gray-100 text-gray-600'] },
                            }" @click.outside="open = false">
                            <x-input-label for="w_company" value="Ettevõte *" />
                            <input id="w_company" name="company" x-model="q" @input="$refs.regcode.value = ''; search()" @focus="results.length && (open = true)"
                                   @keydown.escape="open = false" autocomplete="off" required placeholder="Otsi CRM-ist või äriregistrist…"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <input type="hidden" name="registrikood" x-ref="regcode" value="{{ old('registrikood') }}">
                            <div x-show="open" x-cloak class="absolute z-20 mt-1 w-full max-h-72 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg text-sm">
                                <template x-for="(c, i) in results" :key="i">
                                    <button type="button" @click="pick(c)" class="w-full text-left px-3 py-2 hover:bg-indigo-50 border-b border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs px-1.5 py-0.5 rounded" :class="badge[c.source][1]" x-text="badge[c.source][0]"></span>
                                            <span class="font-medium" x-text="c.company || '(ettevõtteta)'"></span>
                                            <span class="text-xs text-gray-400" x-text="c.registrikood || ''"></span>
                                        </div>
                                        <div class="text-xs text-gray-500" x-text="[[c.first_name, c.last_name].filter(Boolean).join(' '), c.email, c.website].filter(Boolean).join(' · ')"></div>
                                    </button>
                                </template>
                                <div x-show="!loading && results.length === 0" class="px-3 py-2 text-gray-500">Ei leitud — kirjuta nimi käsitsi, luuakse uus ettevõte.</div>
                            </div>
                            <x-input-error :messages="$errors->get('company')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_first_name" value="Eesnimi" />
                            <x-text-input id="w_first_name" name="first_name" :value="old('first_name')" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_last_name" value="Perenimi" />
                            <x-text-input id="w_last_name" name="last_name" :value="old('last_name')" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_email" value="E-post *" />
                            <x-text-input id="w_email" name="email" :value="old('email')" class="mt-1 block w-full" type="email" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_website" value="Koduleht *" />
                            <x-text-input id="w_website" name="website" :value="old('website')" class="mt-1 block w-full" required placeholder="firma.ee" />
                            <x-input-error :messages="$errors->get('website')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_keyword" value="Märksõna *" />
                            <x-text-input id="w_keyword" name="keyword" :value="old('keyword')" class="mt-1 block w-full" required placeholder="nt: katusetööd tartu" />
                            <x-input-error :messages="$errors->get('keyword')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_position" value="Google positsioon" />
                            <x-text-input id="w_position" name="position" :value="old('position')" class="mt-1 block w-full" type="number" min="1" />
                            <x-input-error :messages="$errors->get('position')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_ranking_url" value="Reastuv leht (kui tead)" />
                            <x-text-input id="w_ranking_url" name="ranking_url" :value="old('ranking_url')" class="mt-1 block w-full" placeholder="https://firma.ee/teenus" />
                            <x-input-error :messages="$errors->get('ranking_url')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="w_notes" value="Märkus" />
                            <x-text-input id="w_notes" name="notes" :value="old('notes')" class="mt-1 block w-full" placeholder="nt: helistas, soovib hinnapakkumist" />
                        </div>
                    <div class="md:col-span-3 flex justify-end">
                        <x-primary-button>Lisa ja käivita</x-primary-button>
                    </div>
                </form>
            </details>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold mb-3">Auditeeri leht käsitsi</h3>
                <form method="POST" action="{{ route('seo.audits.store') }}" class="grid md:grid-cols-6 gap-3">
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
                    <div>
                        <x-input-label value="Saidi tüüp" />
                        <select name="site_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Tuvasta ise</option>
                            @foreach(\App\Seo\Services\SiteTypeDetector::LABELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <x-primary-button class="w-full justify-center">Käivita</x-primary-button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-gray-500">Kui annad ainult domeeni ja märksõna, otsib audit ise märksõna lehe. SEO-leadidele, kes vastavad huviga, käivitub audit automaatselt (Playbook → Automaatika).</p>
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
