{{--
    Left rail used by both inbox/index.blade.php (no thread selected) and
    inbox/thread.blade.php (active selection highlighted). Shared so list
    state and filters stay in sync between the two pages.
--}}
<div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col" style="max-height: calc(100vh - 12rem);">

    {{-- Search + filters --}}
    <div class="p-3 border-b border-gray-200 space-y-3 shrink-0">
        <form method="GET" action="{{ route('outreach.inbox.index') }}">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="Otsi…"
                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </form>

        @php
            $chips = [
                'all'        => 'Kõik',
                'unanswered' => 'Vastamata',
                'recent'     => 'Viimased 7p',
                'lead'       => 'Lead',
                'customer'   => 'Klient',
                'watched'    => 'Jälgitavad',
                'archived'   => 'Arhiveeritud',
            ];
        @endphp
        <div class="flex gap-1 text-xs flex-wrap">
            @foreach($chips as $key => $label)
                @php
                    $url = route('outreach.inbox.index', array_filter([
                        'filter' => $key,
                        'q'      => $search ?: null,
                    ]));
                    $active = $filter === $key;
                @endphp
                <a href="{{ $url }}"
                   class="px-2.5 py-1 rounded-full {{ $active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Lead list --}}
    <div class="overflow-y-auto flex-1">
        @forelse($threads as $thread)
            @php
                $encoded = rtrim(strtr(base64_encode($thread->group_email), '+/', '-_'), '=');
                $name    = trim(($thread->lead_first_name ?? '') . ' ' . ($thread->lead_last_name ?? ''));
                if ($name === '') { $name = $thread->display_name ?: $thread->group_email; }
                $isActive = isset($selectedEmail) && $selectedEmail === $thread->group_email;
            @endphp
            @php
                // Urgency color picks the entire row's left border + dot color.
                // Unread state bolds the row's name. Answered + read = neutral.
                $hasUnread = $thread->unread_count > 0;
                $urgencyBorder = match ($thread->urgency) {
                    'red'    => 'border-l-red-500',
                    'yellow' => 'border-l-yellow-500',
                    'green'  => 'border-l-emerald-500',
                    default  => '',
                };
                $urgencyDot = match ($thread->urgency) {
                    'red'    => 'bg-red-500',
                    'yellow' => 'bg-yellow-500',
                    'green'  => 'bg-emerald-500',
                    default  => '',
                };
            @endphp
            <a href="{{ route('outreach.inbox.thread', $encoded) }}"
               class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 border-l-4 {{ $isActive ? 'bg-indigo-50 border-l-indigo-500' : ($urgencyBorder ?: 'border-l-transparent') }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            @if($thread->urgency)
                                <span class="w-2 h-2 rounded-full {{ $urgencyDot }} shrink-0"
                                      title="Vastamata {{ $thread->urgency_hours ?? 0 }}h"></span>
                            @endif
                            <p class="text-sm {{ $hasUnread ? 'font-bold' : 'font-semibold' }} text-gray-900 truncate">{{ $name }}</p>
                            @if($hasUnread)
                                <span class="ml-auto inline-block px-1.5 py-0.5 text-[10px] font-semibold bg-indigo-600 text-white rounded">
                                    {{ $thread->unread_count }} uus
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 mt-0.5">
                            <p class="text-xs text-gray-500 truncate flex-1">{{ $thread->group_email }}</p>
                            @if(! empty($thread->is_customer))
                                <span class="px-1.5 py-0.5 text-[10px] bg-blue-100 text-blue-800 rounded shrink-0">Klient</span>
                            @endif
                            @if(! empty($thread->is_lead) && empty($thread->is_customer))
                                <span class="px-1.5 py-0.5 text-[10px] bg-purple-100 text-purple-800 rounded shrink-0">Lead</span>
                            @endif
                            @if(! empty($thread->is_watched) && empty($thread->is_lead) && empty($thread->is_customer))
                                <span class="px-1.5 py-0.5 text-[10px] bg-amber-100 text-amber-800 rounded shrink-0"
                                      title="{{ $thread->watched_label ?: 'Käsitsi jälgitav' }}">Jälgitav</span>
                            @endif
                        </div>
                        @if($thread->latest_subject)
                            <p class="text-xs {{ $hasUnread ? 'text-gray-900 font-medium' : 'text-gray-700' }} truncate mt-1">{{ $thread->latest_subject }}</p>
                        @endif
                        @if($thread->lead_company)
                            <p class="text-xs text-gray-400 truncate mt-0.5">{{ $thread->lead_company }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs text-gray-400 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($thread->last_received_at)->diffForHumans(null, true) }}
                        </p>
                        @if($thread->reply_count > 1)
                            <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] bg-gray-100 text-gray-700 rounded">{{ $thread->reply_count }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="px-4 py-12 text-center text-gray-500 text-sm">
                @if($search !== '')
                    Otsingule ei leitud vasteid.
                @elseif($filter === 'unanswered')
                    Pole vastamata vestlusi. 🎉
                @elseif($filter === 'recent')
                    Viimase 7 päeva jooksul vastuseid pole.
                @else
                    Vastuseid pole veel.
                @endif
            </div>
        @endforelse
    </div>

    @if($threads->hasPages())
        <div class="p-2 border-t border-gray-200 shrink-0 text-xs">
            {{ $threads->links() }}
        </div>
    @endif
</div>
