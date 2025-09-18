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
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
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
</x-app-layout>
