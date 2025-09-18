<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Muuda Ettevõtet') }} - {{ $company->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('companies.update', $company) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Name -->
                            <div>
                                <x-input-label for="name" :value="__('Ettevõtte nimi')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Email -->
                            <div>
                                <x-input-label for="email" :value="__('E-mail')" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $company->email)" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Phone -->
                            <div>
                                <x-input-label for="phone" :value="__('Telefon')" />
                                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $company->phone)" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>

                            <!-- Website -->
                            <div>
                                <x-input-label for="website" :value="__('Veebileht')" />
                                <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $company->website)" />
                                <x-input-error :messages="$errors->get('website')" class="mt-2" />
                            </div>

                            <!-- Address -->
                            <div>
                                <x-input-label for="address" :value="__('Aadress')" />
                                <textarea id="address" name="address" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $company->address) }}</textarea>
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>

                            <!-- Industry -->
                            <div>
                                <x-input-label for="industry" :value="__('Valdkond')" />
                                <x-text-input id="industry" name="industry" type="text" class="mt-1 block w-full" :value="old('industry', $company->industry)" />
                                <x-input-error :messages="$errors->get('industry')" class="mt-2" />
                            </div>

                            <!-- Size -->
                            <div>
                                <x-input-label for="size" :value="__('Suurus')" />
                                <select id="size" name="size" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali suurus...</option>
                                    <option value="1-10" {{ old('size', $company->size) == '1-10' ? 'selected' : '' }}>1-10 töötajat</option>
                                    <option value="11-50" {{ old('size', $company->size) == '11-50' ? 'selected' : '' }}>11-50 töötajat</option>
                                    <option value="51-200" {{ old('size', $company->size) == '51-200' ? 'selected' : '' }}>51-200 töötajat</option>
                                    <option value="201-500" {{ old('size', $company->size) == '201-500' ? 'selected' : '' }}>201-500 töötajat</option>
                                    <option value="500+" {{ old('size', $company->size) == '500+' ? 'selected' : '' }}>500+ töötajat</option>
                                </select>
                                <x-input-error :messages="$errors->get('size')" class="mt-2" />
                            </div>

                            <!-- Notes -->
                            <div>
                                <x-input-label for="notes" :value="__('Märkused')" />
                                <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $company->notes) }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('companies.show', $company) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
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
</x-app-layout>
