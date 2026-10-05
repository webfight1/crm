<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @include('chats._network', ['t' => $thread])
                {{ $thread->displayName() }}
                @if($thread->phone)<span class="text-sm font-normal text-gray-500 ml-2">+{{ $thread->phone }}</span>@endif
            </h2>
            <a href="{{ route('chats.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Vestlused</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('chats._flash')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Messages --}}
                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-4 space-y-2" style="min-height: 400px;">
                    @if(! $thread->is_monitored)
                        <p class="text-sm text-amber-700 bg-amber-50 rounded p-3">Seda vestlust ei jälgita — sõnumeid ei salvestata. Lülita paremal „Jälgi“ sisse.</p>
                    @endif
                    @forelse($messages as $m)
                        <div class="flex {{ $m->isInbound() ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%] rounded-lg px-3 py-2 {{ $m->isInbound() ? 'bg-gray-100' : 'bg-green-50' }}">
                                @if(($thread->is_group || $thread->network === 'messenger') && $m->isInbound() && $m->sender_name && $m->sender_name !== $thread->name)
                                    <div class="text-xs font-semibold text-gray-600">{{ $m->sender_name }}</div>
                                @endif
                                <div class="text-sm text-gray-900 whitespace-pre-line break-words">{{ $m->body }}</div>
                                <div class="flex items-center justify-end gap-2 mt-1 text-xs text-gray-400">
                                    <span>{{ $m->sent_at->format('d.m.Y H:i') }}</span>
                                    @if($m->task)
                                        <a href="{{ route('tasks.show', $m->task) }}" class="text-indigo-600 hover:underline">✔ ülesanne</a>
                                    @elseif($m->isInbound())
                                        <form method="POST" action="{{ route('chats.messages.task', $m) }}">
                                            @csrf
                                            <button class="text-indigo-600 hover:underline">+ ülesanne</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sõnumeid pole veel salvestatud. Uued sõnumid ilmuvad siia automaatselt.</p>
                    @endforelse
                    <p class="text-xs text-gray-400 text-center pt-2">Vastamiseks kasuta {{ $thread->networkLabel() }}i telefonis — CRM ainult loeb.</p>
                </div>

                {{-- Side panel --}}
                <div class="space-y-4">
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Seosed ja jälgimine</h3>
                        <form method="POST" action="{{ route('chats.update', $thread) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <label class="block text-sm">
                                <span class="text-gray-700">Kontakt</span>
                                <select name="contact_id" class="mt-1 w-full text-sm border-gray-300 rounded">
                                    <option value="">—</option>
                                    @foreach($contacts as $c)
                                        <option value="{{ $c->id }}" @selected($thread->contact_id === $c->id)>{{ $c->full_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm">
                                <span class="text-gray-700">Klient</span>
                                <select name="customer_id" class="mt-1 w-full text-sm border-gray-300 rounded">
                                    <option value="">—</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" @selected($thread->customer_id === $c->id)>{{ $c->full_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_monitored" value="1" @checked($thread->is_monitored) class="rounded border-gray-300">
                                Jälgi (salvesta sõnumid)
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="auto_ai" value="1" @checked($thread->auto_ai) class="rounded border-gray-300" @disabled(! $aiEnabled)>
                                AI automaatselt: uue soovi korral ülesanne + Telegram
                            </label>
                            <button class="px-3 py-1.5 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Salvesta</button>
                        </form>
                        @if($thread->customer)
                            <a href="{{ route('customers.show', $thread->customer) }}" class="block mt-3 text-sm text-indigo-600 hover:underline">→ Kliendi kaart</a>
                        @endif
                    </div>

                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800">🤖 AI analüüs</h3>
                            @if($aiEnabled && $messages->isNotEmpty())
                                <form method="POST" action="{{ route('chats.triage', $thread) }}">
                                    @csrf
                                    <button class="text-xs px-2 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">Analüüsi</button>
                                </form>
                            @endif
                        </div>
                        @if(! $aiEnabled)
                            <p class="text-sm text-gray-500">OPENAI_API_KEY pole seadistatud.</p>
                        @elseif($ai = $thread->ai_triage)
                            <p class="text-xs text-gray-400 mb-2">{{ $thread->triaged_at?->format('d.m H:i') }}</p>
                            <p class="text-sm text-gray-800">{{ $ai['summary'] ?? '' }}</p>
                            @if(! empty($ai['needs_action']) && ! empty($ai['task_title']))
                                <div class="mt-3 p-2 bg-purple-50 rounded text-sm">
                                    <div class="font-medium">{{ $ai['task_title'] }} <span class="text-xs text-purple-700">({{ $ai['priority'] ?? 'medium' }})</span></div>
                                    <div class="text-gray-700 whitespace-pre-line mt-1">{{ $ai['task_description'] ?? '' }}</div>
                                    <form method="POST" action="{{ route('chats.task', $thread) }}" class="mt-2">
                                        @csrf
                                        <button class="text-xs px-2 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Loo ülesanne</button>
                                    </form>
                                </div>
                            @else
                                <p class="mt-2 text-sm text-green-700">Tegevust ei vaja.</p>
                            @endif
                            @if(! empty($ai['reply_draft']))
                                <div class="mt-3" x-data="{ copied: false }">
                                    <div class="text-xs text-gray-500 mb-1">Vastuse mustand</div>
                                    <textarea x-ref="draft" rows="4" class="w-full text-sm border-gray-300 rounded" readonly>{{ $ai['reply_draft'] }}</textarea>
                                    <button type="button" class="text-xs text-indigo-600 hover:underline"
                                            @click="navigator.clipboard.writeText($refs.draft.value); copied = true">
                                        <span x-text="copied ? 'Kopeeritud ✓' : 'Kopeeri'"></span>
                                    </button>
                                </div>
                            @endif
                        @else
                            <p class="text-sm text-gray-500">Vajuta „Analüüsi“ — AI teeb kokkuvõtte, pakub ülesande ja vastuse mustandi.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
