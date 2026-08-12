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
                </div>

                {{-- Kohatäidete abi --}}
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-900 text-sm rounded-lg p-4">
                    <strong>{{ __('Kohatäited kirja tekstis:') }}</strong>
                    <code class="mx-1">{{ '{{nimi}}' }}</code>{{ __('kontakti/kliendi nimi') }},
                    <code class="mx-1">{{ '{{arved}}' }}</code>{{ __('arvete nimekiri') }},
                    <code class="mx-1">{{ '{{summa}}' }}</code>{{ __('tasumata kokku') }},
                    <code class="mx-1">{{ '{{paevad}}' }}</code>{{ __('päeva üle tähtaja') }},
                    <code class="mx-1">{{ '{{ettevote}}' }}</code>{{ __('sinu firma nimi') }}.
                </div>

                {{-- 3 astet --}}
                @foreach([1,2,3] as $l)
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium">{{ $l }}. {{ __('meeldetuletus') }}</h3>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="step{{ $l }}_enabled" value="1" @checked(old("step{$l}_enabled", $settings->{"step{$l}_enabled"})) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">{{ __('Kasutusel') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <x-input-label :for="'step'.$l.'_days'" :value="__('Päeva üle tähtaja')" />
                                <x-text-input :id="'step'.$l.'_days'" :name="'step'.$l.'_days'" type="number" min="0" max="365" class="mt-1 block w-full" :value="old('step'.$l.'_days', $settings->{'step'.$l.'_days'})" required />
                                <x-input-error :messages="$errors->get('step'.$l.'_days')" class="mt-1" />
                            </div>
                            <div class="md:col-span-3">
                                <x-input-label :for="'step'.$l.'_subject'" :value="__('Teema')" />
                                <x-text-input :id="'step'.$l.'_subject'" :name="'step'.$l.'_subject'" type="text" class="mt-1 block w-full" :value="old('step'.$l.'_subject', $settings->{'step'.$l.'_subject'})" />
                                <x-input-error :messages="$errors->get('step'.$l.'_subject')" class="mt-1" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-input-label :for="'step'.$l.'_body'" :value="__('Kirja tekst')" />
                            <textarea id="step{{ $l }}_body" name="step{{ $l }}_body" rows="9" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old("step{$l}_body", $settings->{"step{$l}_body"}) }}</textarea>
                            <x-input-error :messages="$errors->get('step'.$l.'_body')" class="mt-1" />
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
