<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vestlus — {{ $email }}</h2>
                @php
                    // Display metadata cascades through whichever attribution
                    // exists for this thread: lead → customer → contact. A
                    // thread can be customer-only (no lead) when Strategy C
                    // captured fresh business mail from a CRM contact.
                    $primaryLead     = $leads->first();
                    $primaryCustomer = $crmLink['customer'] ?? null;
                    $primaryContact  = $crmLink['contact']  ?? null;

                    $firstName   = $primaryLead?->first_name ?? $primaryCustomer?->first_name ?? $primaryContact?->first_name ?? '';
                    $lastName    = $primaryLead?->last_name  ?? $primaryCustomer?->last_name  ?? $primaryContact?->last_name  ?? '';
                    $displayName = trim($firstName . ' ' . $lastName);

                    $companyName = $primaryLead->company
                                   ?? $primaryCustomer?->company?->name
                                   ?? $primaryContact?->company?->name
                                   ?? null;

                    $primaryReply = \App\Outreach\Models\OutreachEmailAccount::primaryReplyAccount();
                    $lastSubject = $timeline->reverse()->firstWhere('subject') ? $timeline->reverse()->firstWhere(fn($e) => !empty($e->subject))?->subject : null;
                    $replySubjectDefault = $lastSubject
                        ? (str_starts_with(strtolower($lastSubject), 're:') ? $lastSubject : 'Re: ' . $lastSubject)
                        : '';
                @endphp
                <div class="flex items-center gap-2 mt-0.5">
                    @if($displayName !== '' || $companyName)
                        <p class="text-sm text-gray-500">
                            @if($displayName !== ''){{ $displayName }}@endif
                            @if($companyName) · {{ $companyName }}@endif
                        </p>
                    @endif
                    <button type="button"
                            onclick="document.getElementById('contact-edit-form').classList.toggle('hidden')"
                            class="text-xs text-indigo-600 hover:text-indigo-900">
                        ✎ muuda
                    </button>
                </div>

                <form id="contact-edit-form" method="POST"
                      action="{{ route('outreach.inbox.contact', rtrim(strtr(base64_encode($email), '+/', '-_'), '=')) }}"
                      class="hidden mt-3 p-3 bg-gray-50 border border-gray-200 rounded space-y-2">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <div>
                            <label class="text-[11px] text-gray-500 uppercase">Eesnimi</label>
                            <input type="text" name="first_name" value="{{ $firstName }}"
                                   class="mt-0.5 block w-full text-sm border-gray-300 rounded shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="text-[11px] text-gray-500 uppercase">Perenimi</label>
                            <input type="text" name="last_name" value="{{ $lastName }}"
                                   class="mt-0.5 block w-full text-sm border-gray-300 rounded shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="text-[11px] text-gray-500 uppercase">Ettevõte</label>
                            <input type="text" name="company" value="{{ $primaryLead->company ?? '' }}"
                                   class="mt-0.5 block w-full text-sm border-gray-300 rounded shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] text-gray-500">Salvestus uuendab kõiki lead'e selle emailiga + Customer/Contact (v.a ettevõtte FK).</p>
                        <button type="submit"
                                class="px-3 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">
                            Salvesta
                        </button>
                    </div>
                </form>
            </div>
            <div class="flex items-center gap-2">
                @php $encoded = rtrim(strtr(base64_encode($email), '+/', '-_'), '='); @endphp
                @if($isArchived ?? false)
                    <form method="POST" action="{{ route('outreach.inbox.unarchive', $encoded) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded hover:bg-amber-100">
                            ↩ Eemalda arhiivist
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('outreach.inbox.archive', $encoded) }}" class="inline"
                          onsubmit="return confirm('Arhiveeri see vestlus? Saad selle hiljem ‘Arhiveeritud’ filtrist tagasi tuua.');">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 bg-gray-100 border border-gray-300 text-gray-700 text-sm rounded hover:bg-gray-200">
                            🗄 Arhiveeri
                        </button>
                    </form>
                @endif
                <a href="{{ route('outreach.inbox.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Inbox</a>
            </div>
        </div>
    </x-slot>

    @push('scripts')
    <style>
        /* Collapsed-by-default email body. The iframe still grows to its
           natural content height (fitEmailIframes) — the wrapper just
           clips the visible region until the user clicks expand. */
        .email-body-wrapper { max-height: 500px; overflow: hidden; transition: max-height .25s ease; }
        .email-body-wrapper.expanded { max-height: none; }
    </style>
    <script>
        const EMAIL_COLLAPSED_MAX = 500; // px — keep in sync with .email-body-wrapper max-height

        // Auto-grow email-body iframes to fit their content. We re-measure
        // on resize because the iframe's wrapped text reflows when the parent
        // container changes width.
        function fitEmailIframes() {
            document.querySelectorAll('iframe.email-body-iframe').forEach(iframe => {
                try {
                    const doc = iframe.contentDocument;
                    if (! doc || ! doc.body) return;
                    // Use html scrollHeight rather than body — handles bodies
                    // with padding/margin that body alone underestimates.
                    const h = Math.max(
                        doc.body.scrollHeight,
                        doc.documentElement.scrollHeight,
                    );
                    iframe.style.height = (h + 16) + 'px';  // small bottom buffer
                } catch (e) {
                    // contentDocument can be unreadable on some sandbox combinations;
                    // leave the placeholder height in that case.
                }
            });
            // After heights settle, decide whether each wrapper actually
            // overflows — short emails should NOT show the toggle.
            updateEmailOverflowState();
        }

        function updateEmailOverflowState() {
            document.querySelectorAll('.email-body-container').forEach(container => {
                const wrapper = container.querySelector('.email-body-wrapper');
                const iframe  = container.querySelector('iframe.email-body-iframe');
                const btn     = container.querySelector('.email-expand-btn');
                const fade    = container.querySelector('.email-fade');
                if (! wrapper || ! iframe || ! btn || ! fade) return;

                const naturalH = parseInt(iframe.style.height || '0', 10) || iframe.offsetHeight;
                const overflows = naturalH > EMAIL_COLLAPSED_MAX;

                btn.classList.toggle('hidden', ! overflows);
                // Fade overlay only while collapsed AND overflowing.
                const expanded = wrapper.classList.contains('expanded');
                fade.classList.toggle('hidden', ! overflows || expanded);
            });
        }

        function bindEmailExpandButtons() {
            document.querySelectorAll('.email-expand-btn').forEach(btn => {
                if (btn.dataset.bound) return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => {
                    const container = btn.closest('.email-body-container');
                    const wrapper   = container.querySelector('.email-body-wrapper');
                    const fade      = container.querySelector('.email-fade');
                    const expanded  = wrapper.classList.toggle('expanded');
                    btn.textContent = expanded ? '↑ Näita vähem' : '↓ Näita kogu kirja';
                    if (fade) fade.classList.toggle('hidden', expanded);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Iframes may not have loaded yet at DOMContentLoaded — bind onload
            // to each so we resize after their content is parsed.
            document.querySelectorAll('iframe.email-body-iframe').forEach(iframe => {
                iframe.addEventListener('load', fitEmailIframes);
            });
            bindEmailExpandButtons();
            // First-pass resize for any iframe already finished loading
            fitEmailIframes();
        });
        window.addEventListener('resize', fitEmailIframes);

        // ─── Reply-template picker ─────────────────────────────────────────
        // Body is replaced entirely; subject only when currently empty, so
        // half-typed work isn't lost. Reset the select after applying so the
        // operator can pick the same template again later.
        document.addEventListener('DOMContentLoaded', () => {
            const sel = document.getElementById('reply-template');
            if (! sel) return;
            sel.addEventListener('change', (e) => {
                const opt = e.target.selectedOptions[0];
                if (! opt || ! opt.value) return;
                const tplSubject = opt.dataset.subject || '';
                const tplBody    = opt.dataset.body    || '';
                const bodyEl     = document.getElementById('body');
                const subjEl     = document.getElementById('subject');
                if (bodyEl) bodyEl.value = tplBody;
                if (subjEl && tplSubject && subjEl.value.trim() === '') {
                    subjEl.value = tplSubject;
                }
                // Reset picker so the same option can be re-selected later.
                sel.value = '';
                if (bodyEl) bodyEl.focus();
            });
        });
    </script>
    @endpush

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-1 space-y-4">
                    @include('outreach.inbox._watched_panel')
                    @include('outreach.inbox._list')
                </div>

                <div class="lg:col-span-2 space-y-6">

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

            {{-- "Loo ülesanne" — modal pre-filled from the latest inbox
                 message. Customer auto-detected from the thread; deal is
                 optional (existing dropdown, inline-new option, or empty). --}}
            @php
                $latestInbound = $timeline->firstWhere('kind', 'received');
                $threadUrl     = route('outreach.inbox.thread', rtrim(strtr(base64_encode($email), '+/', '-_'), '='));
                $taskTitle     = 'Vastus: ' . ($latestInbound->subject ?? $email);
                // Description is rendered via {!! $task->description !!} on the
                // task show page so an <a> tag becomes a real clickable link.
                // The URL comes from route() which respects APP_URL — currently
                // https://crm.webfight.shop — so the link survives the IP →
                // domain transition cleanly.
                $taskDesc = '<a href="' . e($threadUrl) . '" target="_blank">📬 Vaata kirja inboxis</a>'
                    . "\n\nKlient kirjutas (" . ($latestInbound ? e($latestInbound->subject ?? '—') : '—') . "):\n"
                    . ($latestInbound ? \Illuminate\Support\Str::limit(strip_tags($latestInbound->body_text ?? $latestInbound->body_html ?? ''), 400) : '');
            @endphp
            <div x-data="{
                    open: false,
                    customerId: '{{ $customerForTask?->id }}',
                    customers: @js($allCustomers->map(fn($c) => [
                        'id'      => $c->id,
                        'label'   => trim($c->first_name . ' ' . $c->last_name) . ($c->email ? ' — ' . $c->email : ''),
                    ])->all()),
                    deals: @js($allDeals->map(fn($d) => [
                        'id'          => $d->id,
                        'title'       => $d->title,
                        'stage'       => $d->stage,
                        'customer_id' => $d->customer_id,
                    ])->all()),
                    dealChoice: '',
                    showNewDealTitle: false,
                    get filteredDeals() {
                        return this.customerId
                            ? this.deals.filter(d => String(d.customer_id) === String(this.customerId))
                            : this.deals.slice(0, 15);
                    },
                 }"
                 @keydown.escape.window="open = false">
                <div class="flex justify-end">
                    <button type="button" @click="open = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 hover:bg-amber-200 text-amber-900 text-sm font-medium rounded">
                        📝 {{ __('Loo ülesanne') }}
                    </button>
                </div>

                <div x-show="open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                     @click.self="open = false">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <form method="POST" action="{{ route('outreach.inbox.task', rtrim(strtr(base64_encode($email), '+/', '-_'), '=')) }}">
                            @csrf
                            <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">{{ __('Uus ülesanne sellest threadist') }}</h3>
                                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">×</button>
                            </div>

                            <div class="p-5 space-y-4">
                                <div>
                                    <x-input-label value="Klient" />
                                    <select name="customer_id" x-model="customerId"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— {{ __('vali klient (valikuline)') }} —</option>
                                        <template x-for="c in customers" :key="c.id">
                                            <option :value="c.id" x-text="c.label"></option>
                                        </template>
                                    </select>
                                    @if($customerForTask)
                                        <p class="text-xs text-blue-700 mt-1">✓ {{ __('Auto-tuvastatud thread\'i e-mailist') }} — saad muuta.</p>
                                    @else
                                        <p class="text-xs text-yellow-700 mt-1">{{ __('Klient ei ole automaatselt tuvastatud — vali käsitsi või jäta tühjaks.') }}</p>
                                    @endif
                                </div>

                                <div>
                                    <x-input-label value="Pealkiri" />
                                    <x-text-input name="title" required class="mt-1 block w-full"
                                                  :value="old('title', $taskTitle)" />
                                </div>

                                <div>
                                    <x-input-label value="Kirjeldus" />
                                    <textarea name="description" rows="6"
                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $taskDesc) }}</textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Prioriteet" />
                                        <select name="priority" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach(['low'=>'Madal','medium'=>'Keskmine','high'=>'Kõrge','urgent'=>'Kiireloomuline'] as $v=>$l)
                                                <option value="{{ $v }}" @selected(old('priority','medium')===$v)>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Tähtaeg (valikuline)" />
                                        <x-text-input type="date" name="due_date" class="mt-1 block w-full"
                                                      :value="old('due_date', now()->addDays(3)->format('Y-m-d'))" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Tehing" />
                                    <select name="deal_id" x-model="dealChoice"
                                            @change="showNewDealTitle = (dealChoice === 'new')"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">{{ __('— jäta tühjaks (saab hiljem lisada) —') }}</option>
                                        <optgroup :label="customerId ? 'Valitud kliendi tehingud' : 'Hiljutised tehingud'">
                                            <template x-for="d in filteredDeals" :key="d.id">
                                                <option :value="d.id" x-text="'#' + d.id + ' — ' + d.title + ' (' + d.stage + ')'"></option>
                                            </template>
                                        </optgroup>
                                        <option value="new">+ {{ __('Loo uus tehing') }}</option>
                                    </select>
                                    <p x-show="customerId && filteredDeals.length === 0" x-cloak class="text-xs text-gray-500 mt-1">
                                        {{ __('Sellel kliendil pole veel ühtegi tehingut — loo uus või jäta tühjaks.') }}
                                    </p>
                                </div>

                                <div x-show="showNewDealTitle" x-cloak>
                                    <x-input-label value="Uue tehingu pealkiri" />
                                    <x-text-input name="new_deal_title" class="mt-1 block w-full"
                                                  placeholder="nt: Uus pakkumine — Kliendi nimi" />
                                </div>
                            </div>

                            <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">{{ __('Tühista') }}</button>
                                <x-primary-button>{{ __('Loo ülesanne') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if($leads->isNotEmpty())
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
            @endif

            <div class="space-y-4">
                @forelse($timeline as $entry)
                    @php
                        // Three kinds of timeline entries:
                        //   sent      — campaign step we sent to the lead
                        //   received  — inbound reply from the lead
                        //   crm_reply — manual reply from the CRM (outbound, post-handoff)
                        $kind = $entry->kind;
                        if ($kind === 'received') {
                            $bg = 'bg-purple-50 border-purple-200';
                            $iconBg = 'bg-purple-100 text-purple-700';
                            $icon = '←';
                            $label = 'Vastus kliendilt';
                        } elseif ($kind === 'crm_reply') {
                            $bg = 'bg-emerald-50 border-emerald-200';
                            $iconBg = 'bg-emerald-100 text-emerald-700';
                            $icon = '↪';
                            $label = 'Sinu vastus CRM-ist';
                        } else {
                            $bg = 'bg-white border-gray-200';
                            $iconBg = 'bg-indigo-100 text-indigo-700';
                            $icon = '→';
                            $label = 'Saadetud (samm ' . ($entry->step_order ?? '?') . ')';
                        }
                        $isReceived = $kind === 'received';
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
                                     from the CRM session. allow-same-origin lets the PARENT
                                     read the iframe's body height (for auto-resize), but
                                     scripts INSIDE the iframe stay blocked because
                                     allow-scripts is not granted. Forms, top-nav, plugins
                                     all blocked. A malicious sender cannot run code or
                                     touch the parent.

                                     Wrapper enforces a max-height so long marketing
                                     emails don't push the reply form off-screen; the
                                     "Näita kogu kirja" toggle expands to the full
                                     content. The toggle/fade are hidden until JS
                                     determines the iframe actually overflows. --}}
                                <div class="email-body-container">
                                    <div class="email-body-wrapper relative">
                                        <iframe
                                            sandbox="allow-same-origin"
                                            srcdoc="{{ $entry->body_html }}"
                                            class="w-full border border-gray-200 rounded bg-white email-body-iframe"
                                            style="height: 60px;"
                                            title="Email body"></iframe>
                                        <div class="email-fade hidden absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-white via-white/90 to-transparent pointer-events-none rounded-b"></div>
                                    </div>
                                    <button type="button"
                                            class="email-expand-btn hidden mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                        ↓ Näita kogu kirja
                                    </button>
                                </div>
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

                        {{-- Saved-reply picker. Selecting a template fills body
                             (replaces entirely) and subject (only if empty),
                             so a half-typed reply isn't accidentally wiped.
                             "Halda malle" link opens the management page. --}}
                        @if(($replyTemplates ?? collect())->isNotEmpty())
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <x-input-label for="reply-template" value="Vali vastuse mall" />
                                    <select id="reply-template"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— vali mall, et täita allolev sisu —</option>
                                        @foreach($replyTemplates as $tpl)
                                            <option value="{{ $tpl->id }}"
                                                    data-subject="{{ $tpl->subject }}"
                                                    data-body="{{ $tpl->body }}">{{ $tpl->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <a href="{{ route('outreach.reply-templates.index') }}"
                                   class="text-xs text-indigo-600 hover:text-indigo-800 whitespace-nowrap pb-2">Halda malle →</a>
                            </div>
                        @else
                            <p class="text-xs text-gray-500">
                                Sa pole veel ühtegi vastuse-malli salvestanud.
                                <a href="{{ route('outreach.reply-templates.index') }}" class="text-indigo-600 hover:text-indigo-800">Lisa esimene →</a>
                            </p>
                        @endif

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
                </div> {{-- /lg:col-span-2 --}}
            </div> {{-- /grid --}}
        </div>
    </div>
</x-app-layout>
