<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Muuda aega') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('time-entries.update', $timeEntry) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Task Info -->
                            <div>
                                <x-input-label value="Ülesanne" />
                                <p class="mt-1 text-sm text-gray-600">{{ $timeEntry->task->title }}</p>
                            </div>

                            <!-- Start Time -->
                            <div>
                                <x-input-label value="Algusaeg" />
                                <p class="mt-1 text-sm text-gray-600">{{ $timeEntry->start_time->format('d.m.Y H:i:s') }}</p>
                            </div>

                            <!-- Duration -->
                            <div>
                                <x-input-label :value="__('Kestus')" />
                                <div class="mt-1 flex space-x-4">
                                    <div class="flex-1">
                                        <label for="hours" class="block text-sm font-medium text-gray-700">Tunnid</label>
                                        <input type="number" 
                                            id="hours" 
                                            name="hours" 
                                            min="0"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            value="{{ old('hours', floor($timeEntry->duration)) }}"
                                            required
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <label for="minutes" class="block text-sm font-medium text-gray-700">Minutid</label>
                                        <select id="minutes" 
                                            name="minutes" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            required
                                        >
                                            @foreach(range(0, 59, 5) as $minutes)
                                                <option value="{{ $minutes }}" {{ old('minutes', round(($timeEntry->duration - floor($timeEntry->duration)) * 60)) == $minutes ? 'selected' : '' }}>
                                                    {{ str_pad($minutes, 2, '0', STR_PAD_LEFT) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('hours')" class="mt-2" />
                                <x-input-error :messages="$errors->get('minutes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ url()->previous() }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
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
