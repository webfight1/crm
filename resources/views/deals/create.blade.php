<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Uus tehing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('deals.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tehingu pealkiri -->
                            <div class="md:col-span-2">
                                <x-input-label for="title" :value="__('Tehingu pealkiri')" />
                                <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" required autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Väärtus -->
                            <div>
                                <x-input-label for="value" :value="__('Tehingu väärtus')" />
                                <x-text-input id="value" class="block mt-1 w-full" type="number" name="value" :value="old('value')" min="0" step="0.01" required />
                                <x-input-error :messages="$errors->get('value')" class="mt-2" />
                            </div>

                            <!-- Staatus -->
                            <div>
                                <x-input-label for="stage" :value="__('Staatus')" />
                                <select id="stage" name="stage" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="lead" {{ old('stage') == 'lead' ? 'selected' : '' }}>Potentsiaalne</option>
                                    <option value="qualified" {{ old('stage') == 'qualified' ? 'selected' : '' }}>Kvalifitseeritud</option>
                                    <option value="proposal" {{ old('stage') == 'proposal' ? 'selected' : '' }}>Pakkumine</option>
                                    <option value="negotiation" {{ old('stage') == 'negotiation' ? 'selected' : '' }}>Läbirääkimised</option>
                                    <option value="closed_won" {{ old('stage') == 'closed_won' ? 'selected' : '' }}>Võidetud</option>
                                    <option value="closed_lost" {{ old('stage') == 'closed_lost' ? 'selected' : '' }}>Kaotatud</option>
                                </select>
                                <x-input-error :messages="$errors->get('stage')" class="mt-2" />
                            </div>

                            <!-- Tõenäosus -->
                            <div>
                                <x-input-label for="probability" :value="__('Tõenäosus (%)')" />
                                <x-text-input id="probability" class="block mt-1 w-full" type="number" name="probability" :value="old('probability', 0)" min="0" max="100" required />
                                <x-input-error :messages="$errors->get('probability')" class="mt-2" />
                            </div>

                            <!-- Eeldatav lõpukuupäev -->
                            <div>
                                <x-input-label for="expected_close_date" :value="__('Eeldatav lõpukuupäev')" />
                                <x-text-input id="expected_close_date" class="block mt-1 w-full" type="date" name="expected_close_date" :value="old('expected_close_date')" />
                                <x-input-error :messages="$errors->get('expected_close_date')" class="mt-2" />
                            </div>

                            <!-- Klient -->
                            <div>
                                <x-input-label for="customer_id" :value="__('Klient')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali klient (valikuline)</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <!-- Ettevõte -->
                            <div>
                                <x-input-label for="company_id" :value="__('Ettevõte')" />
                                <select id="company_id" name="company_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali ettevõte (valikuline)</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Kontakt -->
                            <div>
                                <x-input-label for="contact_id" :value="__('Kontakt')" />
                                <select id="contact_id" name="contact_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali kontakt (valikuline)</option>
                                    @foreach($contacts as $contact)
                                        <option value="{{ $contact->id }}" {{ old('contact_id') == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Kirjeldus -->
                        <div class="mt-6">
                            <x-input-label for="description" :value="__('Kirjeldus')" />
                            <textarea id="description" name="description" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Märkused -->
                        <div class="mt-6">
                            <x-input-label for="notes" :value="__('Märkused')" />
                            <textarea id="notes" name="notes" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('deals.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                            <x-primary-button class="ms-4">
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
                // Clean up any previous instances (e.g., after Vite reloads)
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
