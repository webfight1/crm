<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Muuda kliendi kategooriat: {{ $clientAttribute->label }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('client-attributes.update', $clientAttribute) }}">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="name" :value="__('Nimi (süsteemis)')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $clientAttribute->name)" required autofocus />
                                <p class="mt-1 text-sm text-gray-500">Kasutatakse andmebaasis (nt: vip_klient)</p>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="label" :value="__('Silt (kuvatav)')" />
                                <x-text-input id="label" class="block mt-1 w-full" type="text" name="label" :value="old('label', $clientAttribute->label)" required />
                                <p class="mt-1 text-sm text-gray-500">Kuvatakse kasutajaliideses (nt: VIP Klient)</p>
                                <x-input-error :messages="$errors->get('label')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="color" :value="__('Värv')" />
                                <div class="flex items-center mt-1">
                                    <input id="color" type="color" name="color" value="{{ old('color', $clientAttribute->color) }}" class="h-10 w-20 border-gray-300 rounded-md shadow-sm" required />
                                    <x-text-input id="color_text" class="block ml-2 w-full" type="text" value="{{ old('color', $clientAttribute->color) }}" readonly />
                                </div>
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="order" :value="__('Järjekord')" />
                                <x-text-input id="order" class="block mt-1 w-full" type="number" name="order" :value="old('order', $clientAttribute->order)" required min="0" />
                                <p class="mt-1 text-sm text-gray-500">Madalam number kuvatakse esimesena</p>
                                <x-input-error :messages="$errors->get('order')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <div class="flex items-center">
                                    <input id="is_active" type="checkbox" name="is_active" value="1" {{ old('is_active', $clientAttribute->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                        Aktiivne (kuvatakse valikutes)
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('client-attributes.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                            <x-primary-button class="ms-4">
                                {{ __('Uuenda kategooriat') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const colorPicker = document.getElementById('color');
        const colorText = document.getElementById('color_text');
        
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value;
        });
        
        colorText.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    </script>
    @endpush
</x-app-layout>
