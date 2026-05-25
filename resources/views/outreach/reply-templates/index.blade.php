<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vastuste mallid</h2>
            <a href="{{ route('outreach.inbox.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Inbox</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            {{-- New template form --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Uus mall</h3>
                <form method="POST" action="{{ route('outreach.reply-templates.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <x-input-label value="Nimi (kuvatakse dropdown'is)" />
                        <x-text-input name="name" required class="mt-1 block w-full" placeholder="nt: Tänan vastuse eest" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Subjekt (valikuline — täidab subjekti välja)" />
                        <x-text-input name="subject" class="mt-1 block w-full" placeholder="jäta tühjaks kui originaalsubjekt sobib" />
                    </div>
                    <div>
                        <x-input-label value="Sisu" />
                        <textarea name="body" rows="6" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Tere,&#10;&#10;Tänan vastuse eest! …"></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>Salvesta mall</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Existing templates --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Olemasolevad mallid</h3>
                    <span class="text-xs text-gray-500">{{ $templates->count() }} tk</span>
                </div>

                @forelse($templates as $t)
                    <details class="border-b border-gray-100 last:border-b-0">
                        <summary class="cursor-pointer px-6 py-3 hover:bg-gray-50 flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $t->name }}</p>
                                @if($t->subject)
                                    <p class="text-xs text-gray-500 truncate">Subjekt: {{ $t->subject }}</p>
                                @endif
                            </div>
                            <span class="text-xs text-indigo-600 ml-4">muuda ▾</span>
                        </summary>
                        <form method="POST" action="{{ route('outreach.reply-templates.update', $t) }}" class="px-6 py-4 space-y-3 bg-gray-50">
                            @csrf @method('PATCH')
                            <div>
                                <x-input-label value="Nimi" />
                                <x-text-input name="name" required class="mt-1 block w-full" :value="$t->name" />
                            </div>
                            <div>
                                <x-input-label value="Subjekt (valikuline)" />
                                <x-text-input name="subject" class="mt-1 block w-full" :value="$t->subject" />
                            </div>
                            <div>
                                <x-input-label value="Sisu" />
                                <textarea name="body" rows="6" required
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $t->body }}</textarea>
                            </div>
                            <div>
                                <x-input-label value="Järjekord (väiksem = ülemine)" />
                                <x-text-input name="sort_order" type="number" class="mt-1 block w-32" :value="$t->sort_order" />
                            </div>
                            <div class="flex items-center justify-between">
                                <button type="button" onclick="if(confirm('Kustuta mall „{{ addslashes($t->name) }}”?')) document.getElementById('del-{{ $t->id }}').submit()"
                                        class="text-sm text-red-600 hover:text-red-800">× Kustuta</button>
                                <x-primary-button>Salvesta muudatused</x-primary-button>
                            </div>
                        </form>
                        <form id="del-{{ $t->id }}" method="POST" action="{{ route('outreach.reply-templates.destroy', $t) }}" class="hidden">
                            @csrf @method('DELETE')
                        </form>
                    </details>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500">
                        Malle pole veel. Lisa esimene üleval.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
