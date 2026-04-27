<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vestlus — {{ $email }}</h2>
                @php
                    $primary = $leads->first();
                    $displayName = trim(($primary->first_name ?? '') . ' ' . ($primary->last_name ?? ''));
                @endphp
                @if($displayName !== '' || $primary->company)
                    <p class="text-sm text-gray-500 mt-0.5">
                        @if($displayName !== ''){{ $displayName }}@endif
                        @if($primary->company) · {{ $primary->company }}@endif
                    </p>
                @endif
            </div>
            <a href="{{ route('outreach.inbox.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Inbox</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Lead'id ({{ $leads->count() }})</h3>
                <div class="space-y-2">
                    @foreach($leads as $lead)
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <a href="{{ route('outreach.campaigns.show', $lead->campaign) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $lead->campaign->name ?? '—' }}
                                </a>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-gray-600">{{ $lead->status }}</span>
                                @if($lead->replied)
                                    <span class="ml-2 px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">Vastanud</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">via {{ $lead->assignedEmailAccount?->name ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                @forelse($timeline as $entry)
                    @php
                        $isReceived = $entry->kind === 'received';
                        $bg     = $isReceived ? 'bg-purple-50 border-purple-200' : 'bg-white border-gray-200';
                        $label  = $isReceived ? 'Vastus kliendilt' : 'Saadetud (samm ' . ($entry->step_order ?? '?') . ')';
                        $iconBg = $isReceived ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700';
                        $icon   = $isReceived ? '←' : '→';
                    @endphp
                    <div class="border rounded-lg shadow-sm {{ $bg }}">
                        <div class="px-4 py-3 border-b border-gray-200 flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full {{ $iconBg }} flex items-center justify-center text-sm shrink-0">{{ $icon }}</div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900">{{ $label }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5 truncate">
                                        @if($isReceived)
                                            {{ $entry->from_name ? $entry->from_name . ' <' . $entry->from_email . '>' : $entry->from_email }} → {{ $entry->to_email ?? '—' }}
                                        @else
                                            {{ $entry->from_email }} → {{ $entry->to_email }}
                                        @endif
                                    </div>
                                    @if($entry->mailbox_name || $entry->campaign)
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            @if($entry->mailbox_name)Postkast: {{ $entry->mailbox_name }}@endif
                                            @if($entry->campaign) · Kampaania: {{ $entry->campaign }}@endif
                                            @if($entry->has_attachments) · 📎 manus(ed)@endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 text-right shrink-0">
                                {{ optional($entry->occurred_at)->format('d.m.Y H:i') }}
                                <div class="text-gray-400">{{ optional($entry->occurred_at)?->diffForHumans() }}</div>
                            </div>
                        </div>
                        <div class="p-4">
                            @if($entry->subject)
                                <div class="text-sm font-semibold text-gray-800 mb-2">{{ $entry->subject }}</div>
                            @endif
                            @if($entry->body_html)
                                {{-- Sandboxed iframe isolates the email's HTML/CSS/scripts
                                     from the CRM session. sandbox="" disables ALL features
                                     (scripts, forms, top navigation), so a malicious sender's
                                     HTML cannot exfiltrate session cookies or run code. --}}
                                <iframe
                                    sandbox=""
                                    srcdoc="{{ $entry->body_html }}"
                                    class="w-full h-96 border border-gray-200 rounded bg-white"
                                    title="Email body"></iframe>
                            @elseif($entry->body_text)
                                <pre class="whitespace-pre-wrap text-sm text-gray-700 font-sans">{{ $entry->body_text }}</pre>
                            @else
                                <p class="text-sm text-gray-400 italic">— sisu pole salvestatud —</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg p-8 text-center text-gray-500">
                        Vestluses pole veel ühtegi sõnumit.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
