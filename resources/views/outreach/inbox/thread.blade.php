<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vestlus — {{ $email }}</h2>
                @php
                    $primary = $leads->first();
                    $displayName = trim(($primary->first_name ?? '') . ' ' . ($primary->last_name ?? ''));
                    $primaryReply = \App\Outreach\Models\OutreachEmailAccount::primaryReplyAccount();
                    $lastSubject = $timeline->reverse()->firstWhere('subject') ? $timeline->reverse()->firstWhere(fn($e) => !empty($e->subject))?->subject : null;
                    $replySubjectDefault = $lastSubject
                        ? (str_starts_with(strtolower($lastSubject), 're:') ? $lastSubject : 'Re: ' . $lastSubject)
                        : '';
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

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            @if($crmLink['customer'] || $crmLink['contact'])
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-blue-900">🔗 Seotud CRM-i kirjega</h3>
                            <p class="text-sm text-blue-700 mt-1">
                                @if($crmLink['customer'])
                                    Klient:
                                    <a href="{{ route('customers.show', $crmLink['customer']) }}" class="font-medium underline">
                                        {{ $crmLink['customer']->full_name }}
                                    </a>
                                @endif
                                @if($crmLink['contact'])
                                    @if($crmLink['customer']) · @endif
                                    Kontakt:
                                    <a href="{{ route('contacts.show', $crmLink['contact']) }}" class="font-medium underline">
                                        {{ $crmLink['contact']->full_name }}
                                    </a>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endif

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

            {{-- Reply form --}}
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-800">↪ Vasta</span>
                    @if($primaryReply)
                        <span class="text-xs text-gray-500">postkastilt <strong>{{ $primaryReply->email }}</strong></span>
                    @endif
                </div>

                @if(! $primaryReply)
                    <div class="p-4 text-sm text-yellow-800 bg-yellow-50 border-t border-yellow-200">
                        Vastamiseks pole põhipostkasti seadistatud. Mine
                        <a href="{{ route('outreach.accounts.index') }}" class="underline font-medium">Postkastid</a>
                        → vali oma põhi-mailbox (nt veiko@webfight.ee) → märgi "Põhipostkast vastusteks".
                    </div>
                @elseif(! $primaryReply->is_active)
                    <div class="p-4 text-sm text-yellow-800 bg-yellow-50 border-t border-yellow-200">
                        Põhipostkast <strong>{{ $primaryReply->email }}</strong> on hetkel välja lülitatud. Aktiveeri see Postkastid lehel.
                    </div>
                @else
                    <form method="POST" action="{{ route('outreach.inbox.reply', rtrim(strtr(base64_encode($email), '+/', '-_'), '=')) }}" class="p-4 space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="subject" value="Subjekt" />
                            <x-text-input id="subject" name="subject" class="mt-1 block w-full"
                                          :value="old('subject', $replySubjectDefault)" required />
                            <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="body" value="Sõnum" />
                            <textarea id="body" name="body" rows="8" required
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-1" />
                            <p class="text-xs text-gray-500 mt-1">Saadetakse tavalise tekstina (rida-vahetused säilivad).</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400">Vastus säilitab Gmaili threadi (In-Reply-To + References headerid).</p>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Saada vastus</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
