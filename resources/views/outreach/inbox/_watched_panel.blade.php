{{--
    Watched-emails sidebar panel.

    Lets the operator manually whitelist an email address so its inbound
    mail is pulled into the inbox even when the sender isn't an outreach
    lead and isn't yet a CRM Customer/Contact. See
    ReplyDetectionService::detectCrmContacts() and ::scanSingleEmail()
    for the matching + backfill logic.
--}}
<div x-data="{ open: {{ ($watchedAll ?? collect())->isEmpty() ? 'true' : 'false' }} }"
     class="bg-white shadow-sm rounded-lg overflow-hidden">

    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-gray-50">
        <span class="text-sm font-semibold text-gray-800">
            👀 Jälgitavad aadressid
            @if(! ($watchedAll ?? collect())->isEmpty())
                <span class="ml-1 text-xs font-normal text-gray-500">({{ $watchedAll->count() }})</span>
            @endif
        </span>
        <svg class="w-4 h-4 text-gray-500 transition-transform"
             :class="open ? 'rotate-180' : ''"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak class="px-4 pb-4 space-y-3">
        <p class="text-xs text-gray-500">
            Lisa siia kliendi e-mail, kelle kirjad alati siia inboxi tuua — ka siis kui ta pole outreach lead ega CRM-i klient.
        </p>

        <form method="POST" action="{{ route('outreach.inbox.watched.store') }}" class="space-y-2">
            @csrf
            <input type="email" name="email" required
                   placeholder="email@näide.ee"
                   class="w-full text-sm border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
            <input type="text" name="label" maxlength="200"
                   placeholder="Silt (valikuline)"
                   class="w-full text-sm border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
            @error('email')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit"
                    class="w-full px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">
                + Lisa jälgimisele
            </button>
        </form>

        @if(! ($watchedAll ?? collect())->isEmpty())
            <div class="border-t pt-3 space-y-1.5">
                @foreach($watchedAll as $w)
                    <div class="flex items-center gap-2 group">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 truncate" title="{{ $w->email }}">{{ $w->email }}</p>
                            @if($w->label)
                                <p class="text-xs text-gray-500 truncate">{{ $w->label }}</p>
                            @endif
                            @if($w->last_scanned_at)
                                <p class="text-[10px] text-gray-400">Skann: {{ $w->last_scanned_at->diffForHumans() }}</p>
                            @else
                                <p class="text-[10px] text-gray-400">Pole veel skanitud</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('outreach.inbox.watched.destroy', $w) }}"
                              onsubmit="return confirm('Eemalda {{ $w->email }} jälgimisest? Juba imporditud kirjad jäävad alles.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-gray-400 hover:text-red-600 text-lg leading-none px-1"
                                    title="Eemalda jälgimisest">×</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
