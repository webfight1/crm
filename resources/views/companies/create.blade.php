<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Company') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('companies.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Company Name -->
                            <div class="md:col-span-2">
                                <x-input-label for="name" :value="__('Company Name')" />
                                <div class="relative">
                                    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="off" />
                                    <div id="company-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto"></div>
                                </div>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Registry Code -->
                            <div>
                                <x-input-label for="registrikood" :value="__('Registry Code')" />
                                <x-text-input id="registrikood" class="block mt-1 w-full" type="text" name="registrikood" :value="old('registrikood')" />
                                <x-input-error :messages="$errors->get('registrikood')" class="mt-2" />
                            </div>

                            <!-- Email -->
                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Phone -->
                            <div>
                                <x-input-label for="phone" :value="__('Phone')" />
                                <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>

                            <!-- Website -->
                            <div>
                                <x-input-label for="website" :value="__('Website')" />
                                <x-text-input id="website" class="block mt-1 w-full" type="url" name="website" :value="old('website')" placeholder="https://example.com" />
                                <x-input-error :messages="$errors->get('website')" class="mt-2" />
                            </div>

                            <!-- Industry -->
                            <div>
                                <x-input-label for="industry" :value="__('Industry')" />
                                <x-text-input id="industry" class="block mt-1 w-full" type="text" name="industry" :value="old('industry')" />
                                <x-input-error :messages="$errors->get('industry')" class="mt-2" />
                            </div>

                            <!-- Employee Count -->
                            <div>
                                <x-input-label for="employee_count" :value="__('Employee Count')" />
                                <x-text-input id="employee_count" class="block mt-1 w-full" type="number" name="employee_count" :value="old('employee_count')" min="1" />
                                <x-input-error :messages="$errors->get('employee_count')" class="mt-2" />
                            </div>

                            <!-- Annual Revenue -->
                            <div>
                                <x-input-label for="annual_revenue" :value="__('Annual Revenue')" />
                                <x-text-input id="annual_revenue" class="block mt-1 w-full" type="number" name="annual_revenue" :value="old('annual_revenue')" min="0" step="0.01" />
                                <x-input-error :messages="$errors->get('annual_revenue')" class="mt-2" />
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('Status')" />
                                <select id="status" name="status" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="prospect" {{ old('status') == 'prospect' ? 'selected' : '' }}>Prospect</option>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <!-- City -->
                            <div>
                                <x-input-label for="city" :value="__('City')" />
                                <x-text-input id="city" class="block mt-1 w-full" type="text" name="city" :value="old('city')" />
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>

                            <!-- State -->
                            <div>
                                <x-input-label for="state" :value="__('State')" />
                                <x-text-input id="state" class="block mt-1 w-full" type="text" name="state" :value="old('state')" />
                                <x-input-error :messages="$errors->get('state')" class="mt-2" />
                            </div>

                            <!-- Postal Code -->
                            <div>
                                <x-input-label for="postal_code" :value="__('Postal Code')" />
                                <x-text-input id="postal_code" class="block mt-1 w-full" type="text" name="postal_code" :value="old('postal_code')" />
                                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                            </div>

                            <!-- Country -->
                            <div>
                                <x-input-label for="country" :value="__('Country')" />
                                <x-text-input id="country" class="block mt-1 w-full" type="text" name="country" :value="old('country')" />
                                <x-input-error :messages="$errors->get('country')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="mt-6">
                            <x-input-label for="address" :value="__('Address')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div class="mt-6">
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea id="notes" name="notes" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('companies.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <x-primary-button class="ms-4">
                                {{ __('Create Company') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            const suggestionsDiv = document.getElementById('company-suggestions');
            const registrikoodInput = document.getElementById('registrikood');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const websiteInput = document.getElementById('website');
            
            let searchTimeout;
            let selectedCompanyData = null;

            nameInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Puhastame eelmise otsingu timeout
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    suggestionsDiv.classList.add('hidden');
                    return;
                }

                // Viivitame otsingu 300ms, et vältida liiga palju päringuid
                searchTimeout = setTimeout(() => {
                    searchCompanies(query);
                }, 300);
            });

            // Peidame soovitused, kui klikitakse väljaspool
            document.addEventListener('click', function(e) {
                if (!nameInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.classList.add('hidden');
                }
            });

            function searchCompanies(query) {
                fetch(`{{ route('companies.search.external') }}?query=${encodeURIComponent(query)}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Server response:', data);
                    if (Array.isArray(data)) {
                        displaySuggestions(data);
                    } else {
                        console.error('Server did not return an array:', data);
                        suggestionsDiv.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Otsingu viga:', error);
                    suggestionsDiv.classList.add('hidden');
                });
            }

            function displaySuggestions(companies) {
                if (companies.length === 0) {
                    suggestionsDiv.classList.add('hidden');
                    return;
                }

                let html = '';
                companies.forEach(company => {
                    html += `
                        <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 company-suggestion" 
                             data-company='${JSON.stringify(company)}'>
                            <div class="font-medium text-gray-900">${escapeHtml(company.name)}</div>
                            <div class="text-sm text-gray-600">
                                ${company.registrikood ? `Reg: ${escapeHtml(company.registrikood)}` : ''}
                                ${company.kmcode ? ` | KM: ${escapeHtml(company.kmcode)}` : ''}
                                ${company.phone ? ` | Tel: ${escapeHtml(company.phone)}` : ''}
                            </div>
                        </div>
                    `;
                });

                suggestionsDiv.innerHTML = html;
                suggestionsDiv.classList.remove('hidden');

                // Lisame click event listener-id soovitustele
                document.querySelectorAll('.company-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', function() {
                        const companyData = JSON.parse(this.getAttribute('data-company'));
                        selectCompany(companyData);
                    });
                });
            }

            function selectCompany(company) {
                selectedCompanyData = company;
                
                // Täidame vormivälju
                nameInput.value = company.name || '';
                registrikoodInput.value = company.registrikood || '';
                emailInput.value = company.email || '';
                phoneInput.value = company.phone || '';
                websiteInput.value = company.website || '';
                
                // Peidame soovitused
                suggestionsDiv.classList.add('hidden');
                
                // Näitame kasutajale, et andmed on täidetud
                showNotification('Ettevõtte andmed täidetud välisest andmebaasist!');
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function showNotification(message) {
                // Lihtne teade
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-md shadow-lg z-50';
                notification.textContent = message;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        });
    </script>
</x-app-layout>
