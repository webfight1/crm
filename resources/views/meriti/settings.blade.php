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
                        <div class="md:col-span-2">
                            <x-input-label for="company_name" :value="__('Ettevõtte nimi arvel (väljastaja)')" />
                            <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full" :value="old('company_name', $settings->company_name)" placeholder="Kind Studio OÜ" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('Kasutatakse kirjades väljastaja nimena (kohatäide') }} <code>@{{ettevote}}</code>).</p>
                            <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="send_hour" :value="__('Saatmise tund (0–23)')" />
                            <x-text-input id="send_hour" name="send_hour" type="number" min="0" max="23" class="mt-1 block w-full" :value="old('send_hour', $settings->send_hour)" required />
                            <x-input-error :messages="$errors->get('send_hour')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="notify_step" :value="__('Teade Mariusele mis astmes')" />
                            <x-text-input id="notify_step" name="notify_step" type="number" min="1" max="4" class="mt-1 block w-full" :value="old('notify_step', $settings->notify_step)" required />
                            <p class="text-xs text-gray-500 mt-1">{{ __('nt 3 = 3. kirja juures') }}</p>
                            <x-input-error :messages="$errors->get('notify_step')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="attach_from_step" :value="__('PDF alates mis astmest')" />
                            <x-text-input id="attach_from_step" name="attach_from_step" type="number" min="1" max="5" class="mt-1 block w-full" :value="old('attach_from_step', $settings->attach_from_step)" required />
                            <p class="text-xs text-gray-500 mt-1">{{ __('nt 2 = 1. kirjas PDF-i ei ole') }}</p>
                            <x-input-error :messages="$errors->get('attach_from_step')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="handoff_recipient" :value="__('Teavituse saaja (Marius)')" />
                            <x-text-input id="handoff_recipient" name="handoff_recipient" type="email" class="mt-1 block w-full" :value="old('handoff_recipient', $settings->handoff_recipient)" placeholder="marius@kind.ee" />
                            <x-input-error :messages="$errors->get('handoff_recipient')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="from_name" :value="__('Saatja nimi')" />
                            <x-text-input id="from_name" name="from_name" type="text" class="mt-1 block w-full" :value="old('from_name', $settings->from_name)" placeholder="KIND" />
                            <x-input-error :messages="$errors->get('from_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="from_email" :value="__('Saatja e-post (valikuline)')" />
                            <x-text-input id="from_email" name="from_email" type="email" class="mt-1 block w-full" :value="old('from_email', $settings->from_email)" placeholder="{{ config('services.merit.mail.username') ?: config('mail.from.address') }}" />
                            <x-input-error :messages="$errors->get('from_email')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- Kohatäidete abi --}}
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm rounded-lg p-4">
                    <strong>{{ __('Kohatäited kirja tekstis:') }}</strong>
                    <code class="mx-1">@{{arve_nr}}</code>{{ __('arve number') }},
                    <code class="mx-1">@{{ettevote}}</code>{{ __('väljastaja firma') }},
                    <code class="mx-1">@{{tahtaeg}}</code>{{ __('arve tähtaeg') }},
                    <code class="mx-1">@{{summa}}</code>{{ __('arve summa') }},
                    <code class="mx-1">@{{nimi}}</code>{{ __('kliendi/kontakti nimi') }}.
                </div>

                {{-- 4 kirja: iga arve saab need astmed oma päevade järgi --}}
                @php $labels = [1 => __('1. kiri (maksetähtajal)'), 2 => __('2. kiri'), 3 => __('3. kiri'), 4 => __('4. kiri (viimane)')]; @endphp
                @foreach([1,2,3,4] as $l)
                    @php
                        $step = $settings->step($l);
                        $fDays = 'step'.$l.'_days'; $fSubject = 'step'.$l.'_subject'; $fBody = 'step'.$l.'_body';
                    @endphp
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4 gap-4">
                            <h3 class="text-lg font-medium">{{ $labels[$l] }}</h3>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600" for="{{ $fDays }}">{{ __('päeva üle tähtaja:') }}</label>
                                <x-text-input :id="$fDays" :name="$fDays" type="number" min="0" max="365" class="w-24" :value="old($fDays, $step['days'])" required />
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get($fDays)" class="mb-2" />

                        <div>
                            <x-input-label :for="$fSubject" :value="__('Teema')" />
                            <x-text-input :id="$fSubject" :name="$fSubject" type="text" class="mt-1 block w-full" :value="old($fSubject, $step['subject'])" />
                            <x-input-error :messages="$errors->get($fSubject)" class="mt-1" />
                        </div>

                        <div class="mt-4">
                            <x-input-label :for="$fBody" :value="__('Kirja tekst')" />
                            <textarea id="{{ $fBody }}" name="{{ $fBody }}" rows="9" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old($fBody, $step['body']) }}</textarea>
                            <x-input-error :messages="$errors->get($fBody)" class="mt-1" />
                        </div>
                    </div>
                @endforeach

                {{-- Testrežiim --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <x-input-label for="test_recipient" :value="__('🧪 Testrežiim — test-saaja e-post')" class="font-medium text-amber-900" />
                        <x-text-input id="test_recipient" name="test_recipient" type="email" class="mt-1 block w-full" :value="old('test_recipient', $settings->test_recipient)" placeholder="{{ __('nt sinu@email.ee — jäta tühjaks päris saatmiseks') }}" />
                        <p class="text-xs text-amber-800 mt-2">
                            {{ __('Kui täidetud, lähevad KÕIK meeldetuletused (ja Mariuse teated) sellele aadressile, mitte päris klientidele, ega muuda olekut. Päris saatmiseks jäta tühjaks.') }}
                        </p>
                        <x-input-error :messages="$errors->get('test_recipient')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit">{{ __('Salvesta seaded') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
