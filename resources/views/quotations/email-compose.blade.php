<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Saada pakkumine e-postiga') }} — #{{ $quotation->number }}
            </h2>
            <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-indigo-600 hover:text-indigo-900">← {{ __('Tagasi pakkumisele') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4"
             x-data="{
                 accountId: '{{ old('account_id', $sender?->id) }}',
                 accounts: @js($accounts->mapWithKeys(fn($a) => [$a->id => [
                     'email'     => $a->email,
                     'name'      => $a->name,
                     'signature' => $a->signature_html ?? '',
                 ]])->all()),
                 get current() { return this.accounts[this.accountId] || null; },
             }">

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            @if($accounts->isEmpty())
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                    {{ __('Aktiivset SMTP-postkasti pole. Mine') }} <a href="{{ route('outreach.accounts.index') }}" class="underline">/outreach/accounts</a>
                    {{ __('ja seadista vähemalt üks postkast SMTP saatmiseks (Gmail / üldine SMTP).') }}
                    <p class="text-xs mt-1">{{ __('NB: Zone Relay tüüpi postkast ei toeta PDF manuseid pakkumistel.') }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="space-y-4 bg-white shadow-sm rounded-lg p-6">
                    @csrf

                    {{-- Saatja --}}
                    <div>
                        <x-input-label value="Saatja (kontoga, kust kiri läheb)" />
                        <select name="account_id" x-model="accountId" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->name }} &lt;{{ $a->email }}&gt;</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Valitud postkasti jalus lisatakse automaatselt kirja lõppu.') }}</p>
                        <x-input-error :messages="$errors->get('account_id')" class="mt-1" />
                    </div>

                    {{-- Saaja --}}
                    <div>
                        <x-input-label value="Saaja e-mail" />
                        <x-text-input type="email" name="to" class="mt-1 block w-full" required
                                      :value="old('to', $recipientEmail)" />
                        <x-input-error :messages="$errors->get('to')" class="mt-1" />
                    </div>

                    {{-- Subjekt --}}
                    <div>
                        <x-input-label value="Subjekt" />
                        <x-text-input name="subject" class="mt-1 block w-full" required
                                      :value="old('subject', $defaultSubject)" />
                        <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                    </div>

                    {{-- Sisu --}}
                    <div>
                        <x-input-label value="Kirja sisu" />
                        <textarea name="body" rows="14" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-sans text-sm leading-relaxed">{{ old('body', $defaultBody) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Rea-vahetused säilivad, tekst HTML-itakse automaatselt (linkide nupp pole nimeliselt toetatud).') }}</p>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>

                    {{-- Mis lisatakse: PDF + jalus --}}
                    <div class="bg-gray-50 border border-gray-200 rounded p-4 space-y-3">
                        <p class="text-sm font-medium text-gray-700">{{ __('Mis lisatakse automaatselt') }}:</p>

                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-lg">📎</span>
                            <span class="font-mono">{{ $pdfFilename }}</span>
                            <span class="text-xs text-gray-500">— {{ __('PDF genereeritakse saatmise hetkel ja lisatakse manusena') }}</span>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-1">{{ __('Jalus, mis kirja lõppu lisatakse') }} ({{ __('saatja kontolt') }}):</p>
                            <div class="bg-white border border-gray-200 rounded p-3 text-sm"
                                 x-html="current && current.signature ? current.signature : '<span class=&quot;text-gray-400 italic&quot;>(selle kontoga pole jalust seadistatud — kiri läheb ilma)</span>'"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('quotations.show', $quotation) }}"
                           class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">{{ __('Tühista') }}</a>
                        <x-primary-button>{{ __('Saada e-postiga') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
