<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Meeldetuletuste seaded') }}
            </h2>
            <a href="{{ route('meriti.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Tagasi') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('meriti.settings.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                {{-- Üldseaded --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ __('Üldseaded') }}</h3>

                    <label class="inline-flex items-center mb-4">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('Automaatne saatmine sees') }}</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="min_overdue_days" :value="__('Alampiir: päeva üle tähtaja')" />
                            <x-text-input id="min_overdue_days" name="min_overdue_days" type="number" min="0" max="365" class="mt-1 block w-full" :value="old('min_overdue_days', $settings->min_overdue_days)" required />
                            <x-input-error :messages="$errors->get('min_overdue_days')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="min_days_between" :value="__('Min vahe kahe kirja vahel (päeva)')" />
                            <x-text-input id="min_days_between" name="min_days_between" type="number" min="0" max="365" class="mt-1 block w-full" :value="old('min_days_between', $settings->min_days_between)" required />
                            <x-input-error :messages="$errors->get('min_days_between')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="send_hour" :value="__('Saatmise tund (0–23)')" />
                            <x-text-input id="send_hour" name="send_hour" type="number" min="0" max="23" class="mt-1 block w-full" :value="old('send_hour', $settings->send_hour)" required />
                            <x-input-error :messages="$errors->get('send_hour')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="from_name" :value="__('Saatja nimi')" />
                            <x-text-input id="from_name" name="from_name" type="text" class="mt-1 block w-full" :value="old('from_name', $settings->from_name)" placeholder="{{ __('nt Minu Firma OÜ') }}" />
                            <x-input-error :messages="$errors->get('from_name')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="from_email" :value="__('Saatja e-post (valikuline)')" />
                            <x-text-input id="from_email" name="from_email" type="email" class="mt-1 block w-full" :value="old('from_email', $settings->from_email)" placeholder="{{ config('mail.from.address') }}" />
                            <x-input-error :messages="$errors->get('from_email')" class="mt-1" />
                        </div>
                    </div>

                    <hr class="my-6">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="attach_pdfs" value="1" @checked(old('attach_pdfs', $settings->attach_pdfs)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Lisa arved PDF-manusena') }}</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Tõmbab arved Meritist ja lisab kirjale manusena.') }}</p>
                        </div>
                        <div>
                            <x-input-label for="max_attachments" :value="__('Manuste ülempiir (arvet kirja kohta)')" />
                            <x-text-input id="max_attachments" name="max_attachments" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('max_attachments', $settings->max_attachments)" required />
                            <p class="text-xs text-gray-500 mt-1">{{ __('Kui arveid on rohkem, saadetakse ainult nimekiri (ilma manusteta).') }}</p>
                            <x-input-error :messages="$errors->get('max_attachments')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <x-input-label for="test_recipient" :value="__('🧪 Testrežiim — test-saaja e-post')" class="font-medium text-amber-900" />
                        <x-text-input id="test_recipient" name="test_recipient" type="email" class="mt-1 block w-full" :value="old('test_recipient', $settings->test_recipient)" placeholder="{{ __('nt sinu@email.ee — jäta tühjaks päris saatmiseks') }}" />
                        <p class="text-xs text-amber-800 mt-2">
                            {{ __('Kui täidetud, lähevad KÕIK meeldetuletused sellele aadressile (mitte päris klientidele) ega muuda olekut/logisid. Ideaalne testimiseks. Päris saatmiseks jäta tühjaks.') }}
                        </p>
                        <x-input-error :messages="$errors->get('test_recipient')" class="mt-1" />
                    </div>
                </div>

                {{-- Kohatäidete abi --}}
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm rounded-lg p-4">
                    <strong>{{ __('Kohatäited kirja tekstis:') }}</strong>
                    <code class="mx-1">@{{nimi}}</code>{{ __('kontakti/kliendi nimi') }},
                    <code class="mx-1">@{{arved}}</code>{{ __('arvete nimekiri') }},
                    <code class="mx-1">@{{summa}}</code>{{ __('tasumata kokku') }},
                    <code class="mx-1">@{{paevad}}</code>{{ __('päeva üle tähtaja') }},
                    <code class="mx-1">@{{ettevote}}</code>{{ __('sinu firma nimi') }}.
                </div>

                {{-- 3 astet --}}
                @foreach([1,2,3] as $l)
                    @php
                        $step = $settings->step($l);
                        $fEnabled = 'step'.$l.'_enabled';
                        $fDays    = 'step'.$l.'_days';
                        $fSubject = 'step'.$l.'_subject';
                        $fBody    = 'step'.$l.'_body';
                    @endphp
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium">{{ $l }}. {{ __('meeldetuletus') }}</h3>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="{{ $fEnabled }}" value="1" @checked(old($fEnabled, $step['enabled'])) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Kasutusel') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <x-input-label :for="$fDays" :value="__('Päeva üle tähtaja')" />
                                <x-text-input :id="$fDays" :name="$fDays" type="number" min="0" max="365" class="mt-1 block w-full" :value="old($fDays, $step['days'])" required />
                                <x-input-error :messages="$errors->get($fDays)" class="mt-1" />
                            </div>
                            <div class="md:col-span-3">
                                <x-input-label :for="$fSubject" :value="__('Teema')" />
                                <x-text-input :id="$fSubject" :name="$fSubject" type="text" class="mt-1 block w-full" :value="old($fSubject, $step['subject'])" />
                                <x-input-error :messages="$errors->get($fSubject)" class="mt-1" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-input-label :for="$fBody" :value="__('Kirja tekst')" />
                            <textarea id="{{ $fBody }}" name="{{ $fBody }}" rows="9" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old($fBody, $step['body']) }}</textarea>
                            <x-input-error :messages="$errors->get($fBody)" class="mt-1" />
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <x-primary-button type="submit">{{ __('Salvesta seaded') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
