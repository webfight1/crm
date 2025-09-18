<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Customer: ') . $customer->full_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('customers.update', $customer) }}">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- First Name -->
                            <div>
                                <x-input-label for="first_name" :value="__('First Name')" />
                                <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name" :value="old('first_name', $customer->first_name)" required autofocus />
                                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                            </div>

                            <!-- Last Name -->
                            <div>
                                <x-input-label for="last_name" :value="__('Last Name')" />
                                <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name" :value="old('last_name', $customer->last_name)" required />
                                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                            </div>

                            <!-- Email -->
                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $customer->email)" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Phone -->
                            <div>
                                <x-input-label for="phone" :value="__('Phone')" />
                                <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone', $customer->phone)" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>

                            <!-- Company -->
                            <div>
                                <x-input-label for="company_id" :value="__('Company')" />
                                <select id="company_id" name="company_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select a company (optional)</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id', $customer->company_id) == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('Status')" />
                                <select id="status" name="status" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="prospect" {{ old('status', $customer->status) == 'prospect' ? 'selected' : '' }}>Prospect</option>
                                    <option value="active" {{ old('status', $customer->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $customer->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <!-- Date of Birth -->
                            <div>
                                <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
                                <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth" :value="old('date_of_birth', $customer->date_of_birth?->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                            </div>

                            <!-- City -->
                            <div>
                                <x-input-label for="city" :value="__('City')" />
                                <x-text-input id="city" class="block mt-1 w-full" type="text" name="city" :value="old('city', $customer->city)" />
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>

                            <!-- State -->
                            <div>
                                <x-input-label for="state" :value="__('State')" />
                                <x-text-input id="state" class="block mt-1 w-full" type="text" name="state" :value="old('state', $customer->state)" />
                                <x-input-error :messages="$errors->get('state')" class="mt-2" />
                            </div>

                            <!-- Postal Code -->
                            <div>
                                <x-input-label for="postal_code" :value="__('Postal Code')" />
                                <x-text-input id="postal_code" class="block mt-1 w-full" type="text" name="postal_code" :value="old('postal_code', $customer->postal_code)" />
                                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                            </div>

                            <!-- Country -->
                            <div>
                                <x-input-label for="country" :value="__('Country')" />
                                <x-text-input id="country" class="block mt-1 w-full" type="text" name="country" :value="old('country', $customer->country)" />
                                <x-input-error :messages="$errors->get('country')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="mt-6">
                            <x-input-label for="address" :value="__('Address')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $customer->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div class="mt-6">
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea id="notes" name="notes" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $customer->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('customers.show', $customer) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <x-primary-button class="ms-4">
                                {{ __('Update Customer') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
