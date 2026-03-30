<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Uus Ülesanne') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('tasks.store') }}">
                        @csrf

                        <div class="grid md:grid-cols-4   gap-6">
                            <!-- Title -->
                            <div class="md:col-span-2">
                                <x-input-label for="title" :value="__('Pealkiri')" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Deal -->
                            <div class="md:col-span-1">
                                <x-input-label for="deal_id" :value="__('Tehing *')" />
                                <select id="deal_id" name="deal_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali tehing...</option>
                                    @foreach($deals as $deal)
                                        <option value="{{ $deal->id }}" {{ old('deal_id') == $deal->id ? 'selected' : '' }}>
                                            {{ $deal->title }} (€{{ number_format($deal->value, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('deal_id')" class="mt-2" />
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-3">
                                <x-input-label for="description" :value="__('Kirjeldus')" />
                                <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <!-- Type -->
                            <div>
                                <x-input-label for="type" :value="__('Tüüp')" />
                                <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="call" {{ old('type') == 'call' ? 'selected' : '' }}>Kõne</option>
                                    <option value="email" {{ old('type') == 'email' ? 'selected' : '' }}>E-mail</option>
                                    <option value="meeting" {{ old('type') == 'meeting' ? 'selected' : '' }}>Kohtumine</option>
                                    <option value="follow_up" {{ old('type') == 'follow_up' ? 'selected' : '' }}>Järelkontroll</option>
                                    <option value="development" {{ old('type') == 'development' ? 'selected' : '' }}>Arendus</option>
                                    <option value="bug_fix" {{ old('type') == 'bug_fix' ? 'selected' : '' }}>Parandus</option>
                                    <option value="content_creation" {{ old('type') == 'content_creation' ? 'selected' : '' }}>Sisu lisamine</option>
                                    <option value="proposal_creation" {{ old('type') == 'proposal_creation' ? 'selected' : '' }}>Pakkumise koostamine</option>
                                    <option value="testing" {{ old('type') == 'testing' ? 'selected' : '' }}>Testimine</option>
                                    <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Muu</option>
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>

                            <!-- Priority -->
                            <div>
                                <x-input-label for="priority" :value="__('Prioriteet')" />
                                <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Madal</option>
                                    <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Keskmine</option>
                                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Kõrge</option>
                                    <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Kiire</option>
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('Staatus')" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Ootel</option>
                                    <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>Töös</option>
                                    <option value="needs_testing" {{ old('status') == 'needs_testing' ? 'selected' : '' }}>Vajab testimist</option>
                                    <option value="needs_clarification" {{ old('status') == 'needs_clarification' ? 'selected' : '' }}>Vajab täpsustust</option>
                                    <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Valmis</option>
                                    <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Tühistatud</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <!-- Due Date -->
                            <div>
                                <x-input-label for="due_date" :value="__('Tähtaeg')" />
                                <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            </div>

                            <!-- Customer -->
                            <div>
                                <x-input-label for="customer_id" :value="__('Klient (valikuline)')" />
                                <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali klient...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <!-- Company -->
                            <div>
                                <x-input-label for="company_id" :value="__('Ettevõte *')" />
                                <select id="company_id" name="company_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali ettevõte...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Assignee -->
                            <div>
                                <x-input-label for="assignee_id" :value="__('Vastutaja *')" />
                                <select id="assignee_id" name="assignee_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali vastutaja...</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('assignee_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('assignee_id')" class="mt-2" />
                            </div>



                            <!-- Price -->
                            <div>
                                <x-input-label for="price" :value="__('Hind (€)')" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price')" required />
                                <x-input-error :messages="$errors->get('price')" class="mt-2" />
                            </div>

                            <!-- Work Type -->
                            <div>
                                <x-input-label for="work_type" :value="__('Töö tüüp')" />
                                <select id="work_type" name="work_type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali...</option>
                                    <option value="technical" {{ old('work_type') == 'technical' ? 'selected' : '' }}>Tehniline</option>
                                    <option value="design" {{ old('work_type') == 'design' ? 'selected' : '' }}>Disain</option>
                                    <option value="copywriting" {{ old('work_type') == 'copywriting' ? 'selected' : '' }}>Tekstid</option>
                                    <option value="marketing" {{ old('work_type') == 'marketing' ? 'selected' : '' }}>Turundus</option>
                                    <option value="ecommerce" {{ old('work_type') == 'ecommerce' ? 'selected' : '' }}>E-kaubandus</option>
                                    <option value="website" {{ old('work_type') == 'website' ? 'selected' : '' }}>Veebileht</option>
                                    <option value="project" {{ old('work_type') == 'project' ? 'selected' : '' }}>Projekt</option>
                                    <option value="maintenance" {{ old('work_type') == 'maintenance' ? 'selected' : '' }}>Hooldus</option>
                                    <option value="other" {{ old('work_type') == 'other' ? 'selected' : '' }}>Muu</option>
                                </select>
                                <x-input-error :messages="$errors->get('work_type')" class="mt-2" />
                            </div>

                            <!-- Clarity Level -->
                            <div>
                                <x-input-label for="clarity_level" :value="__('Selgus')" />
                                <select id="clarity_level" name="clarity_level" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali...</option>
                                    <option value="clear" {{ old('clarity_level') == 'clear' ? 'selected' : '' }}>✅ Selge</option>
                                    <option value="medium" {{ old('clarity_level') == 'medium' ? 'selected' : '' }}>Keskmine</option>
                                    <option value="vague" {{ old('clarity_level') == 'vague' ? 'selected' : '' }}>❓ Ebaselge</option>
                                </select>
                                <x-input-error :messages="$errors->get('clarity_level')" class="mt-2" />
                            </div>

                            <!-- Revenue Model -->
                            <div>
                                <x-input-label for="revenue_model" :value="__('Tulu mudel')" />
                                <select id="revenue_model" name="revenue_model" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali...</option>
                                    <option value="hourly_partner" {{ old('revenue_model') == 'hourly_partner' ? 'selected' : '' }}>🔥 Tunnitasu partner</option>
                                    <option value="fixed_project" {{ old('revenue_model') == 'fixed_project' ? 'selected' : '' }}>Fikseeritud projekt</option>
                                    <option value="retainer" {{ old('revenue_model') == 'retainer' ? 'selected' : '' }}>Püsiklient</option>
                                    <option value="internal" {{ old('revenue_model') == 'internal' ? 'selected' : '' }}>Sisemine</option>
                                    <option value="uncertain" {{ old('revenue_model') == 'uncertain' ? 'selected' : '' }}>Ebakindel</option>
                                </select>
                                <x-input-error :messages="$errors->get('revenue_model')" class="mt-2" />
                            </div>

                            <!-- Cashflow Speed -->
                            <div>
                                <x-input-label for="cashflow_speed" :value="__('Raha kiirus')" />
                                <select id="cashflow_speed" name="cashflow_speed" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali...</option>
                                    <option value="fast" {{ old('cashflow_speed') == 'fast' ? 'selected' : '' }}>⚡ Kiire</option>
                                    <option value="medium" {{ old('cashflow_speed') == 'medium' ? 'selected' : '' }}>Keskmine</option>
                                    <option value="slow" {{ old('cashflow_speed') == 'slow' ? 'selected' : '' }}>🐌 Aeglane</option>
                                </select>
                                <x-input-error :messages="$errors->get('cashflow_speed')" class="mt-2" />
                            </div>

                            <!-- Risk Level -->
                            <div>
                                <x-input-label for="risk_level" :value="__('Riski tase')" />
                                <select id="risk_level" name="risk_level" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali...</option>
                                    <option value="low" {{ old('risk_level') == 'low' ? 'selected' : '' }}>Madal</option>
                                    <option value="medium" {{ old('risk_level') == 'medium' ? 'selected' : '' }}>Keskmine</option>
                                    <option value="high" {{ old('risk_level') == 'high' ? 'selected' : '' }}>⚠️ Kõrge</option>
                                </select>
                                <x-input-error :messages="$errors->get('risk_level')" class="mt-2" />
                            </div>

                            <!-- Estimated Hours -->
                            <div>
                                <x-input-label for="estimated_hours" :value="__('Hinnanguline aeg (tunnid)')" />
                                <x-text-input id="estimated_hours" name="estimated_hours" type="number" min="0" class="mt-1 block w-full" :value="old('estimated_hours')" />
                                <x-input-error :messages="$errors->get('estimated_hours')" class="mt-2" />
                            </div>

                            <!-- Value Score -->
                            <div>
                                <x-input-label for="value_score" :value="__('Väärtuse skoor (1-10)')" />
                                <x-text-input id="value_score" name="value_score" type="number" min="1" max="10" class="mt-1 block w-full" :value="old('value_score')" />
                                <x-input-error :messages="$errors->get('value_score')" class="mt-2" />
                            </div>

                            <!-- Cashflow Score -->
                            <div>
                                <x-input-label for="cashflow_score" :value="__('Rahavoo skoor (1-10)')" />
                                <x-text-input id="cashflow_score" name="cashflow_score" type="number" min="1" max="10" class="mt-1 block w-full" :value="old('cashflow_score')" />
                                <x-input-error :messages="$errors->get('cashflow_score')" class="mt-2" />
                            </div>

                            <!-- Is Quick Win -->
                            <div>
                                <div class="flex items-center mt-4">
                                    <input id="is_quick_win" type="checkbox" name="is_quick_win" value="1" {{ old('is_quick_win') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <label for="is_quick_win" class="ml-2 block text-sm text-gray-900">
                                        ⚡ Kiire võit
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('is_quick_win')" class="mt-2" />
                            </div>

                            <!-- Is Blocking -->
                            <div>
                                <div class="flex items-center mt-4">
                                    <input id="is_blocking" type="checkbox" name="is_blocking" value="1" {{ old('is_blocking') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <label for="is_blocking" class="ml-2 block text-sm text-gray-900">
                                        🚫 Blokeeriv ülesanne
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('is_blocking')" class="mt-2" />
                            </div>

                            <!-- Recommended Next Step -->
                            <div class="md:col-span-3">
                                <x-input-label for="recommended_next_step" :value="__('Soovitatud järgmine samm')" />
                                <x-text-input id="recommended_next_step" name="recommended_next_step" type="text" class="mt-1 block w-full" :value="old('recommended_next_step')" />
                                <x-input-error :messages="$errors->get('recommended_next_step')" class="mt-2" />
                            </div>

                            <!-- Notes -->
                            <div class="md:col-span-3">
                                <x-input-label for="notes" :value="__('Märkused')" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('tasks.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
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

    @push('scripts')
    <script>
        function getElementSafely(id) {
            const element = document.getElementById(id);
            if (!element) {
                console.warn(`Element with ID '${id}' not found`);
            }
            return element;
        }

        function setValueIfExists(elementId, value) {
            const element = getElementSafely(elementId);
            if (element && value !== undefined && value !== null) {
                element.value = value;
            }
        }

        function fetchDealDetails(dealId) {
            if (!dealId) {
                return;
            }

            // Show loading state
            const dealSelect = getElementSafely('deal_id');
            if (!dealSelect) return;

            const originalValue = dealSelect.innerHTML;
            dealSelect.disabled = true;
            dealSelect.innerHTML = '<option value="">Laen tehingu andmeid...</option>';

            fetch(`/deals/${dealId}/details`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Viga andmete laadimisel');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Deal data:', data);
                    
                    // Set customer if exists
                    if (data.customer_id) {
                        setValueIfExists('customer_id', data.customer_id);
                    }
                    
                    // Set company if exists
                    if (data.company_id) {
                        setValueIfExists('company_id', data.company_id);
                    }
                    
                    // Set contact if exists
                    if (data.contact_id) {
                        setValueIfExists('contact_id', data.contact_id);
                    }
                    
                    // Set default assignee (first admin)
                    @if(isset($defaultAssignee))
                        setValueIfExists('assignee_id', '{{ $defaultAssignee->id }}');
                    @endif
                })
                .catch(error => {
                    console.error('Viga tehingu andmete laadimisel:', error);
                })
                .finally(() => {
                    // Reset the deal select
                    if (dealSelect) {
                        dealSelect.disabled = false;
                        dealSelect.innerHTML = originalValue;
                        dealSelect.value = dealId;
                    }
                });
        }

        // Initialize the form with deal data if a deal is already selected on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Tom Select for deal_id
            if (document.getElementById('deal_id')) {
                new TomSelect('#deal_id', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: 'Vali tehing...',
                    onChange: function(value) {
                        if (value) {
                            fetchDealDetails(value);
                        }
                    }
                });
            }
            
            // Initialize Tom Select for customer_id
            if (document.getElementById('customer_id')) {
                new TomSelect('#customer_id', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: 'Vali klient...'
                });
            }
            
            // Initialize Tom Select for company_id
            if (document.getElementById('company_id')) {
                new TomSelect('#company_id', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: 'Vali ettevõte...'
                });
            }
            
            // Initialize Tom Select for contact_id
            if (document.getElementById('contact_id')) {
                new TomSelect('#contact_id', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: 'Vali kontakt...'
                });
            }
            
            // Initialize Tom Select for assignee_id
            if (document.getElementById('assignee_id')) {
                new TomSelect('#assignee_id', {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: 'Vali vastutaja...'
                });
            }
            
            const dealId = getElementSafely('deal_id')?.value;
            if (dealId) {
                fetchDealDetails(dealId);
            }
        });
    </script>
    @endpush

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
