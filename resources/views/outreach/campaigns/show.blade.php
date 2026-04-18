<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $campaign->name }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $campaign->is_active ? 'Aktiivne' : 'Peatatud' }}
                    @if($campaign->daily_limit) · Päevalimiit: {{ $campaign->daily_limit }} @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('outreach.campaigns.leads.index', $campaign) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm rounded hover:bg-gray-50">
                    Kõik leadid
                </a>
                <a href="{{ route('outreach.logs.index', $campaign) }}" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm rounded hover:bg-gray-50">
                    Saatmislogi
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Left: Settings + Steps --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Campaign settings --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Kampaania seaded</h3>
                        <form method="POST" action="{{ route('outreach.campaigns.update', $campaign) }}" class="space-y-4">
                            @csrf @method('PATCH')
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="name" value="Nimi" />
                                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $campaign->name)" required />
                                </div>
                                <div>
                                    <x-input-label for="daily_limit" value="Päevalimiit" />
                                    <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" :value="old('daily_limit', $campaign->daily_limit)" placeholder="piiramatu" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="description" value="Kirjeldus" />
                                <textarea id="description" name="description" rows="2"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $campaign->description) }}</textarea>
                            </div>
                            <div class="flex flex-col gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="reply_stop_enabled" value="1" @checked(old('reply_stop_enabled', $campaign->reply_stop_enabled)) class="rounded border-gray-300 text-indigo-600">
                                    <span class="text-sm text-gray-700">Peata vastuse korral</span>
                                </label>
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" name="use_ai_line" value="1" @checked(old('use_ai_line', $campaign->use_ai_line)) class="rounded border-gray-300 text-indigo-600 mt-0.5">
                                    <span class="text-sm text-gray-700">
                                        AI isikupärastamine
                                        <span class="block text-xs text-gray-400">Kasuta <code class="bg-gray-100 px-1 rounded">&#123;&#123;ai_line&#125;&#125;</code> meilimallides.</span>
                                    </span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $campaign->is_active)) class="rounded border-gray-300 text-indigo-600">
                                    <span class="text-sm text-gray-700">Aktiivne</span>
                                </label>
                            </div>
                            <div>
                                <x-input-label for="ai_prompt" value="AI prompt (valikuline)" />
                                <textarea id="ai_prompt" name="ai_prompt" rows="5"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                    placeholder="Jäta tühjaks vaikimisi promti kasutamiseks...">{{ old('ai_prompt', $campaign->ai_prompt) }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">
                                    Muutujad promptis:
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;company&#125;&#125;</code>
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;website&#125;&#125;</code>
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;industry&#125;&#125;</code>
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;first_name&#125;&#125;</code>
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;last_name&#125;&#125;</code>
                                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;email&#125;&#125;</code>
                                </p>
                            </div>
                            <x-primary-button>Salvesta</x-primary-button>
                        </form>
                    </div>

                    {{-- Steps --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Järjestuse sammud</h3>

                        @forelse($campaign->steps->sortBy('step_order') as $step)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <form method="POST" action="{{ route('outreach.campaigns.steps.update', [$campaign, $step]) }}">
                                @csrf @method('PATCH')
                                <div class="grid grid-cols-4 gap-3 mb-3">
                                    <div>
                                        <x-input-label value="Samm #" />
                                        <x-text-input name="step_order" type="number" class="mt-1 block w-full" :value="$step->step_order" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Päev alates registreerimisest" />
                                        <x-text-input name="day_offset" type="number" class="mt-1 block w-full" :value="$step->day_offset" required />
                                    </div>
                                    <div class="col-span-2">
                                        <x-input-label value="Teema" />
                                        <x-text-input name="subject" class="mt-1 block w-full" :value="$step->subject" required />
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <x-input-label value="Sisu (HTML)" />
                                    <textarea name="body_template" rows="4"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ $step->body_template }}</textarea>
                                    <p class="text-xs text-gray-400 mt-1">Muutujad: &#123;&#123;first_name&#125;&#125; &#123;&#123;last_name&#125;&#125; &#123;&#123;company&#125;&#125; &#123;&#123;website&#125;&#125;</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <x-primary-button>Salvesta samm</x-primary-button>
                                    <button type="button" onclick="
                                        if(confirm('Kustuta samm?')) {
                                            document.getElementById('delete-step-{{ $step->id }}').submit();
                                        }
                                    " class="text-red-600 hover:text-red-900 text-sm">Kustuta</button>
                                </div>
                            </form>
                            <form id="delete-step-{{ $step->id }}" method="POST" action="{{ route('outreach.campaigns.steps.destroy', [$campaign, $step]) }}" class="hidden">
                                @csrf @method('DELETE')
                            </form>
                        </div>
                        @empty
                        <p class="text-gray-500 text-sm mb-4">Samme pole veel lisatud.</p>
                        @endforelse

                        {{-- Add step --}}
                        <div class="border border-dashed border-gray-300 rounded-lg p-4 mt-4">
                            <p class="text-sm font-medium text-gray-700 mb-3">+ Lisa samm</p>
                            <form method="POST" action="{{ route('outreach.campaigns.steps.store', $campaign) }}">
                                @csrf
                                <div class="grid grid-cols-4 gap-3 mb-3">
                                    <div>
                                        <x-input-label value="Samm #" />
                                        <x-text-input name="step_order" type="number" class="mt-1 block w-full" :value="$campaign->steps->count() + 1" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Päev" />
                                        <x-text-input name="day_offset" type="number" class="mt-1 block w-full" value="0" required />
                                    </div>
                                    <div class="col-span-2">
                                        <x-input-label value="Teema" />
                                        <x-text-input name="subject" class="mt-1 block w-full" required />
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <x-input-label value="Sisu (HTML)" />
                                    <textarea name="body_template" rows="4"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" required></textarea>
                                </div>
                                <x-primary-button>Lisa samm</x-primary-button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Right: Add lead + recent logs --}}
                <div class="space-y-6">

                    {{-- Add lead --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Lisa lead</h3>
                        <form method="POST" action="{{ route('outreach.campaigns.leads.store', $campaign) }}" class="space-y-3">
                            @csrf
                            <div>
                                <x-input-label value="Eesnimi *" />
                                <x-text-input name="first_name" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label value="Perekonnanimi" />
                                <x-text-input name="last_name" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="E-post *" />
                                <x-text-input name="email" type="email" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label value="Ettevõte" />
                                <x-text-input name="company" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Veebileht" />
                                <x-text-input name="website" type="url" class="mt-1 block w-full" placeholder="https://" />
                            </div>
                            <x-primary-button class="w-full justify-center">Lisa lead</x-primary-button>
                        </form>
                    </div>

                    {{-- CSV import --}}
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex items-start justify-between mb-1">
                            <h3 class="font-medium text-gray-900">Impordi CSV</h3>
                            <a href="{{ route('outreach.leads.csv-template') }}"
                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                Lae näidis CSV
                            </a>
                        </div>
                        <p class="text-xs text-gray-500 mb-4">Veerud: <code class="bg-gray-100 px-1 rounded">email, first_name, last_name, company, website, industry, lcp_mobile, performance_score, notes, qualification</code><br><span class="text-gray-400">qualification: <code>lead</code> (vaikimisi) või <code>skip</code> — skip-read ei saadeta.</span></p>

                        @if($errors->has('csv_file'))
                            <div class="mb-3 text-sm text-red-600">{{ $errors->first('csv_file') }}</div>
                        @endif

                        <form method="POST" action="{{ route('outreach.leads.import') }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                            <div>
                                <input type="file" name="csv_file" accept=".csv,text/csv"
                                    class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                            </div>
                            <x-primary-button>Impordi</x-primary-button>
                        </form>
                    </div>

                    {{-- Recent logs --}}
                    @if($recentLogs->count())
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-gray-900">Viimased saatmised</h3>
                            <a href="{{ route('outreach.logs.index', $campaign) }}" class="text-xs text-indigo-600 hover:underline">Kõik</a>
                        </div>
                        <div class="space-y-2">
                            @foreach($recentLogs->take(10) as $log)
                            <div class="flex items-center justify-between text-sm">
                                <div class="truncate max-w-[180px]">
                                    <span class="text-gray-900">{{ $log->lead?->email ?? $log->to_email }}</span>
                                    <span class="text-gray-400 ml-1">#{{ $log->step_order }}</span>
                                </div>
                                <span class="px-1.5 py-0.5 rounded text-xs
                                    {{ $log->status === 'sent'    ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $log->status === 'failed'  ? 'bg-red-100 text-red-700'   : '' }}
                                    {{ $log->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $log->status === 'skipped' ? 'bg-gray-100 text-gray-600' : '' }}
                                ">{{ $log->status }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
