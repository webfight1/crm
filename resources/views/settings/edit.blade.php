<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Firma seaded') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Põhiandmed -->
                            <div>
                                <h3 class="text-lg font-medium mb-4">{{ __('Põhiandmed') }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="company_name" :value="__('Firma nimi')" />
                                        <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full" :value="old('company_name', $settings->company_name)" required />
                                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="registration_number" :value="__('Registrikood')" />
                                        <x-text-input id="registration_number" name="registration_number" type="text" class="mt-1 block w-full" :value="old('registration_number', $settings->registration_number)" />
                                        <x-input-error :messages="$errors->get('registration_number')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="vat_number" :value="__('Käibemaksukohustuslase number')" />
                                        <x-text-input id="vat_number" name="vat_number" type="text" class="mt-1 block w-full" :value="old('vat_number', $settings->vat_number)" />
                                        <x-input-error :messages="$errors->get('vat_number')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="address" :value="__('Aadress')" />
                                        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $settings->address)" />
                                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Kontaktandmed -->
                            <div>
                                <h3 class="text-lg font-medium mb-4">{{ __('Kontaktandmed') }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="phone" :value="__('Telefon')" />
                                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $settings->phone)" />
                                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="email" :value="__('E-post')" />
                                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $settings->email)" />
                                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="website" :value="__('Veebileht')" />
                                        <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $settings->website)" />
                                        <x-input-error :messages="$errors->get('website')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Pangaandmed -->
                            <div>
                                <h3 class="text-lg font-medium mb-4">{{ __('Pangaandmed') }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="bank_name" :value="__('Panga nimi')" />
                                        <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $settings->bank_name)" />
                                        <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="bank_account" :value="__('Pangakonto')" />
                                        <x-text-input id="bank_account" name="bank_account" type="text" class="mt-1 block w-full" :value="old('bank_account', $settings->bank_account)" />
                                        <x-input-error :messages="$errors->get('bank_account')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="swift" :value="__('SWIFT/BIC')" />
                                        <x-text-input id="swift" name="swift" type="text" class="mt-1 block w-full" :value="old('swift', $settings->swift)" />
                                        <x-input-error :messages="$errors->get('swift')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Pakkumise seaded -->
                            <div>
                                <h3 class="text-lg font-medium mb-4">{{ __('Pakkumise seaded') }}</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="default_vat_rate" :value="__('Vaikimisi käibemaksu määr (%)')" />
                                        <x-text-input id="default_vat_rate" name="default_vat_rate" type="number" class="mt-1 block w-full" :value="old('default_vat_rate', $settings->default_vat_rate)" step="0.01" required />
                                        <x-input-error :messages="$errors->get('default_vat_rate')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="quotation_terms" :value="__('Vaikimisi maksetingimused')" />
                                        <textarea id="quotation_terms" name="quotation_terms" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('quotation_terms', $settings->quotation_terms) }}</textarea>
                                        <x-input-error :messages="$errors->get('quotation_terms')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="logo" :value="__('Logo')" />
                                        @if($settings->logo_path)
                                            <div class="mt-2 mb-4">
                                                <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="Company Logo" class="h-12">
                                            </div>
                                        @endif
                                        <input type="file" id="logo" name="logo" accept="image/*" class="mt-1 block w-full">
                                        <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>
                                {{ __('Salvesta muudatused') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
