<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Lisa sündmus') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('calendar.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Title -->
                            <div>
                                <x-input-label for="title" :value="__('Pealkiri')" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Description -->
                            <div>
                                <x-input-label for="description" :value="__('Kirjeldus')" />
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <!-- Start Time -->
                            <div>
                                <x-input-label for="start_time" :value="__('Algus')" />
                                <x-text-input id="start_time" name="start_time" type="datetime-local" class="mt-1 block w-full" :value="old('start_time')" required />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                            </div>

                            <!-- End Time -->
                            <div>
                                <x-input-label for="end_time" :value="__('Lõpp')" />
                                <x-text-input id="end_time" name="end_time" type="datetime-local" class="mt-1 block w-full" :value="old('end_time')" required />
                                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                            </div>

                            <!-- Location -->
                            <div>
                                <x-input-label for="location" :value="__('Asukoht')" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location')" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>

                            <!-- Type -->
                            <div>
                                <x-input-label for="type" :value="__('Tüüp')" />
                                <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="meeting" {{ old('type') == 'meeting' ? 'selected' : '' }}>Kohtumine</option>
                                    <option value="call" {{ old('type') == 'call' ? 'selected' : '' }}>Kõne</option>
                                    <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Muu</option>
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>

                            <!-- Task -->
                            <div>
                                <x-input-label for="task_id" :value="__('Seotud ülesanne')" />
                                <select id="task_id" name="task_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali ülesanne...</option>
                                    @foreach($tasks as $task)
                                        <option value="{{ $task->id }}" {{ old('task_id') == $task->id ? 'selected' : '' }}>
                                            {{ $task->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('task_id')" class="mt-2" />
                            </div>

                            <!-- Attendees -->
                            <div>
                                <x-input-label for="attendees" :value="__('Osalejad')" />
                                <select id="attendees" name="attendees[]" multiple class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ in_array($user->id, old('attendees', [])) ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('attendees')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">Hoia all Ctrl (Windows) või Cmd (Mac) klahvi, et valida mitu osalejat</p>
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('Staatus')" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="confirmed" {{ old('status') == 'confirmed' ? 'selected' : '' }}>Kinnitatud</option>
                                    <option value="tentative" {{ old('status') == 'tentative' ? 'selected' : '' }}>Esialgne</option>
                                    <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Tühistatud</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('calendar.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                {{ __('Tühista') }}
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
