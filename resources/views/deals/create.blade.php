<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Deal') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('deals.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Deal Title -->
                            <div class="md:col-span-2">
                                <x-input-label for="title" :value="__('Deal Title')" />
                                <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" required autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Value -->
                            <div>
                                <x-input-label for="value" :value="__('Deal Value')" />
                                <x-text-input id="value" class="block mt-1 w-full" type="number" name="value" :value="old('value')" min="0" step="0.01" required />
                                <x-input-error :messages="$errors->get('value')" class="mt-2" />
                            </div>

                            <!-- Stage -->
                            <div>
                                <x-input-label for="stage" :value="__('Stage')" />
                                <select id="stage" name="stage" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="lead" {{ old('stage') == 'lead' ? 'selected' : '' }}>Lead</option>
                                    <option value="qualified" {{ old('stage') == 'qualified' ? 'selected' : '' }}>Qualified</option>
                                    <option value="proposal" {{ old('stage') == 'proposal' ? 'selected' : '' }}>Proposal</option>
                                    <option value="negotiation" {{ old('stage') == 'negotiation' ? 'selected' : '' }}>Negotiation</option>
                                    <option value="closed_won" {{ old('stage') == 'closed_won' ? 'selected' : '' }}>Closed Won</option>
                                    <option value="closed_lost" {{ old('stage') == 'closed_lost' ? 'selected' : '' }}>Closed Lost</option>
                                </select>
                                <x-input-error :messages="$errors->get('stage')" class="mt-2" />
                            </div>

                            <!-- Probability -->
                            <div>
                                <x-input-label for="probability" :value="__('Probability (%)')" />
                                <x-text-input id="probability" class="block mt-1 w-full" type="number" name="probability" :value="old('probability', 0)" min="0" max="100" required />
                                <x-input-error :messages="$errors->get('probability')" class="mt-2" />
                            </div>

                            <!-- Expected Close Date -->
                            <div>
                                <x-input-label for="expected_close_date" :value="__('Expected Close Date')" />
                                <x-text-input id="expected_close_date" class="block mt-1 w-full" type="date" name="expected_close_date" :value="old('expected_close_date')" />
                                <x-input-error :messages="$errors->get('expected_close_date')" class="mt-2" />
                            </div>

                            <!-- Customer -->
                            <div>
                                <x-input-label for="customer_id" :value="__('Customer')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select a customer (optional)</option>
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
                                <x-input-label for="company_id" :value="__('Company')" />
                                <select id="company_id" name="company_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select a company (optional)</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Contact -->
                            <div>
                                <x-input-label for="contact_id" :value="__('Contact')" />
                                <select id="contact_id" name="contact_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select a contact (optional)</option>
                                    @foreach($contacts as $contact)
                                        <option value="{{ $contact->id }}" {{ old('contact_id') == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('contact_id')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-6">
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div class="mt-6">
                            <x-input-label for="notes" :value="__('Notes')" />
                            <textarea id="notes" name="notes" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('deals.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <x-primary-button class="ms-4">
                                {{ __('Create Deal') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
