<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Muuda Tehingut') }} - {{ $deal->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('deals.update', $deal) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Title -->
                            <div>
                                <x-input-label for="title" :value="__('Pealkiri')" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $deal->title)" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Description -->
                            <div>
                                <x-input-label for="description" :value="__('Kirjeldus')" />
                                <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $deal->description) }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <!-- Value -->
                            <div>
                                <x-input-label for="value" :value="__('Väärtus (€)')" />
                                <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('value', $deal->value)" required />
                                <x-input-error :messages="$errors->get('value')" class="mt-2" />
                            </div>

                            <!-- Stage -->
                            <div>
                                <x-input-label for="stage" :value="__('Etapp')" />
                                <select id="stage" name="stage" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="lead" {{ old('stage', $deal->stage) == 'lead' ? 'selected' : '' }}>Potentsiaalne klient</option>
                                    <option value="qualified" {{ old('stage', $deal->stage) == 'qualified' ? 'selected' : '' }}>Kvalifitseeritud</option>
                                    <option value="proposal" {{ old('stage', $deal->stage) == 'proposal' ? 'selected' : '' }}>Pakkumine</option>
                                    <option value="negotiation" {{ old('stage', $deal->stage) == 'negotiation' ? 'selected' : '' }}>Läbirääkimised</option>
                                    <option value="closed_won" {{ old('stage', $deal->stage) == 'closed_won' ? 'selected' : '' }}>Võidetud</option>
                                    <option value="closed_lost" {{ old('stage', $deal->stage) == 'closed_lost' ? 'selected' : '' }}>Kaotatud</option>
                                </select>
                                <x-input-error :messages="$errors->get('stage')" class="mt-2" />
                            </div>

                            <!-- Probability -->
                            <div>
                                <x-input-label for="probability" :value="__('Tõenäosus (%)')" />
                                <x-text-input id="probability" name="probability" type="number" min="0" max="100" class="mt-1 block w-full" :value="old('probability', $deal->probability)" required />
                                <x-input-error :messages="$errors->get('probability')" class="mt-2" />
                            </div>

                            <!-- Expected Close Date -->
                            <div>
                                <x-input-label for="expected_close_date" :value="__('Eeldatav sulgemise kuupäev')" />
                                <x-text-input id="expected_close_date" name="expected_close_date" type="date" class="mt-1 block w-full" :value="old('expected_close_date', $deal->expected_close_date?->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('expected_close_date')" class="mt-2" />
                            </div>

                            <!-- Actual Close Date -->
                            <div>
                                <x-input-label for="actual_close_date" :value="__('Tegelik sulgemise kuupäev')" />
                                <x-text-input id="actual_close_date" name="actual_close_date" type="date" class="mt-1 block w-full" :value="old('actual_close_date', $deal->actual_close_date?->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('actual_close_date')" class="mt-2" />
                            </div>

                            <!-- Customer -->
                            <div>
                                <x-input-label for="customer_id" :value="__('Klient (valikuline)')" />
                                <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali klient...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id', $deal->customer_id) == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <!-- Company -->
                            <div>
                                <x-input-label for="company_id" :value="__('Ettevõte (valikuline)')" />
                                <select id="company_id" name="company_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali ettevõte...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id', $deal->company_id) == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Contact -->
                            <div>
                                <x-input-label for="contact_id" :value="__('Kontakt (valikuline)')" />
                                <select id="contact_id" name="contact_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali kontakt...</option>
                                    @foreach($contacts as $contact)
                                        <option value="{{ $contact->id }}" {{ old('contact_id', $deal->contact_id) == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->first_name }} {{ $contact->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('contact_id')" class="mt-2" />
                            </div>

                            <!-- Notes -->
                            <div>
                                <x-input-label for="notes" :value="__('Märkused')" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $deal->notes) }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('deals.show', $deal) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                            <x-primary-button>
                                {{ __('Salvesta') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Rich Text Editor (TinyMCE) -->
    <style>
        /* Ensure TinyMCE area is always interactive */
        .tox .tox-edit-area__overlay { display: none !important; }
        .tox .tox-edit-area__iframe { pointer-events: auto !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        window.addEventListener('load', function() {
            if (window.tinymce) {
                tinymce.remove();
            }
            const commonOptions = {
                plugins: 'lists link code fullscreen table',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link table | code | fullscreen',
                menubar: false,
                branding: false,
                statusbar: true,
                height: 320,
                convert_urls: false,
                skin: 'oxide',
                content_css: 'default',
                content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
                readonly: false,
                promotion: false,
                license_key: 'gpl',
            };

            if (document.querySelector('textarea#description')) {
                tinymce.init({
                    selector: 'textarea#description',
                    ...commonOptions,
                    setup: (editor) => {
                        editor.on('init', () => {
                            try { editor.getBody().setAttribute('contenteditable', true); } catch (e) {}
                        });
                    }
                });
            }
            if (document.querySelector('textarea#notes')) {
                tinymce.init({
                    selector: 'textarea#notes',
                    ...commonOptions,
                });
            }
        });
    </script>
</x-app-layout>
