<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Uus Ülesanne') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($errors->any())
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                            <ul class="list-disc list-inside text-sm">
                                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('tasks.store') }}" class="space-y-5">
                        @csrf

                        {{-- Title --}}
                        <div>
                            <x-input-label for="title" :value="__('Pealkiri')" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>

                        {{-- Description --}}
                        <div>
                            <x-input-label for="description" :value="__('Kirjeldus')" />
                            <textarea id="description" name="description" rows="4"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-1" />
                        </div>

                        <div class="grid md:grid-cols-3 gap-4">
                            {{-- Type --}}
                            <div>
                                <x-input-label for="type" :value="__('Tüüp')" />
                                <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach([
                                        'other'=>'Muu','call'=>'Kõne','email'=>'E-mail','meeting'=>'Kohtumine',
                                        'follow_up'=>'Järelkontroll','development'=>'Arendus','bug_fix'=>'Parandus',
                                        'content_creation'=>'Sisu lisamine','proposal_creation'=>'Pakkumise koostamine','testing'=>'Testimine',
                                    ] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('type','other')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Priority — default Madal --}}
                            <div>
                                <x-input-label for="priority" :value="__('Prioriteet')" />
                                <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach(['low'=>'Madal','medium'=>'Keskmine','high'=>'Kõrge','urgent'=>'Kiire'] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('priority','low')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Status — default Ootel --}}
                            <div>
                                <x-input-label for="status" :value="__('Staatus')" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach([
                                        'pending'=>'Ootel','in_progress'=>'Töös','needs_testing'=>'Vajab testimist',
                                        'needs_clarification'=>'Vajab täpsustust','completed'=>'Valmis','cancelled'=>'Tühistatud',
                                    ] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('status','pending')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            {{-- Due date — optional --}}
                            <div>
                                <x-input-label for="due_date" :value="__('Tähtaeg (valikuline)')" />
                                <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" />
                            </div>

                            {{-- Price --}}
                            <div>
                                <x-input-label for="price" :value="__('Hind (€)')" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price', '0')" />
                            </div>
                        </div>

                        <div class="grid md:grid-cols-3 gap-4">
                            {{-- Customer — TomSelect for search, default Webfight --}}
                            <div>
                                <x-input-label for="customer_id" :value="__('Klient')" />
                                <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— jäta tühjaks —</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}"
                                                @selected(old('customer_id', $defaultCustomer?->id) == $customer->id)>
                                            {{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Company — default Webfight OÜ --}}
                            <div>
                                <x-input-label for="company_id" :value="__('Ettevõte')" />
                                <select id="company_id" name="company_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— jäta tühjaks —</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}"
                                                @selected(old('company_id', $defaultCompany?->id) == $company->id)>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Assignee — default logged-in user --}}
                            <div>
                                <x-input-label for="assignee_id" :value="__('Vastutaja')" />
                                <select id="assignee_id" name="assignee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}"
                                                @selected(old('assignee_id', auth()->id()) == $user->id)>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <x-input-label for="notes" :value="__('Märkused (valikuline)')" />
                            <textarea id="notes" name="notes" rows="3"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="{{ route('tasks.index') }}"
                               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">{{ __('Tühista') }}</a>
                            <x-primary-button>{{ __('Salvesta') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-500">
                {{ __('Detailsemad väljad (töö tüüp, tehing, kontakt, kliendi lisainfo) leiad ülesande muutmise vaates.') }}
            </p>
        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (! window.TomSelect) return;
            const opts = { create: false, sortField: { field: 'text', direction: 'asc' } };
            ['customer_id','company_id','assignee_id'].forEach(id => {
                const el = document.getElementById(id);
                if (el && ! el.tomselect) new TomSelect(el, opts);
            });
        });
    </script>
    @endpush
</x-app-layout>
