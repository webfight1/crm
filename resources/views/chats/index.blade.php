<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vestlused — WhatsApp &amp; Messenger</h2>
            <a href="{{ route('chats.connect') }}" class="text-sm text-indigo-600 hover:text-indigo-900">⚙ Ühendus</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('chats._flash')

            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-4 py-3 border-b">
                    <h3 class="font-semibold text-gray-800">Jälgitavad kliendivestlused</h3>
                    <p class="text-xs text-gray-500">WhatsAppis seotakse kontakt telefoni järgi, Messengeris täpse nime järgi — need lisatakse siia automaatselt. Sõnumeid salvestatakse ainult nendest vestlustest.</p>
                </div>
                @forelse($monitored as $t)
                    <a href="{{ route('chats.show', $t) }}" class="flex items-start gap-3 px-4 py-3 border-b last:border-0 hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                @include('chats._network', ['t' => $t])
                                <span class="font-medium text-gray-900">{{ $t->displayName() }}</span>
                                @if($t->is_group)<span class="text-xs bg-gray-100 text-gray-600 px-1.5 rounded">grupp</span>@endif
                                @if($t->auto_ai)<span class="text-xs bg-purple-100 text-purple-700 px-1.5 rounded">AI</span>@endif
                                @if($t->unread_count)<span class="text-xs bg-green-600 text-white px-1.5 rounded-full">{{ $t->unread_count }}</span>@endif
                            </div>
                            <div class="text-sm text-gray-600 truncate">
                                @if($t->lastMessage)
                                    {{ $t->lastMessage->direction === 'out' ? 'Mina: ' : '' }}{{ \Illuminate\Support\Str::limit($t->lastMessage->body, 120) }}
                                @else
                                    <span class="text-gray-400">Uusi sõnumeid pole veel tulnud</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 whitespace-nowrap">{{ $t->last_message_at?->format('d.m H:i') }}</div>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-500">Veel pole ühtegi jälgitavat vestlust.</p>
                @endforelse
            </div>

            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-4 py-3 border-b flex items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800">Muud vestlused</h3>
                        <p class="text-xs text-gray-500">Ainult nimi ja viimase sõnumi aeg, sisu ei salvestata. Vali „Jälgi“, kui see on klient.</p>
                    </div>
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Nimi või number" class="text-sm border-gray-300 rounded">
                    </form>
                </div>
                @forelse($others as $t)
                    <div class="flex items-center gap-3 px-4 py-2 border-b last:border-0">
                        <div class="flex-1 min-w-0 text-sm">
                            @include('chats._network', ['t' => $t])
                            <span class="text-gray-900">{{ $t->displayName() }}</span>
                            @if($t->phone && $t->name)<span class="text-gray-400 ml-1">+{{ $t->phone }}</span>@endif
                            @if($t->is_group)<span class="text-xs bg-gray-100 text-gray-600 px-1.5 rounded ml-1">grupp</span>@endif
                        </div>
                        <div class="text-xs text-gray-400">{{ $t->last_message_at?->format('d.m H:i') }}</div>
                        <form method="POST" action="{{ route('chats.monitor', $t) }}">
                            @csrf
                            <button class="text-xs px-2 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Jälgi</button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-500">Pole.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
