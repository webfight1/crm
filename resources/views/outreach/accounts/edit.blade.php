<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Muuda postkasti: {{ $account->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">

                @if($account->consecutive_failures > 0)
                <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                    <strong>Vead:</strong> {{ $account->consecutive_failures }} järjestikust ebaõnnestumist.
                    @if($account->last_error)
                        Viimane viga: <span class="font-mono text-xs">{{ Str::limit($account->last_error, 120) }}</span>
                    @endif
                </div>
                @endif

                <form method="POST" action="{{ route('outreach.accounts.update', $account) }}" class="space-y-6">
                    @csrf @method('PATCH')

                    <div>
                        <x-input-label for="name" value="Kuvanimi" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $account->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <p class="text-sm text-gray-500">E-posti aadress: <strong>{{ $account->email }}</strong> (ei saa muuta)</p>

                    <fieldset class="border border-gray-200 rounded p-4">
                        <legend class="text-sm font-medium text-gray-700 px-1">SMTP seaded</legend>
                        <div class="grid grid-cols-2 gap-4 mt-2">
                            <div>
                                <x-input-label for="smtp_host" value="Host" />
                                <x-text-input id="smtp_host" name="smtp_host" class="mt-1 block w-full" :value="old('smtp_host', $account->smtp_host)" required />
                            </div>
                            <div>
                                <x-input-label for="smtp_port" value="Port" />
                                <x-text-input id="smtp_port" name="smtp_port" type="number" class="mt-1 block w-full" :value="old('smtp_port', $account->smtp_port)" required />
                            </div>
                            <div>
                                <x-input-label for="smtp_username" value="Kasutajanimi" />
                                <x-text-input id="smtp_username" name="smtp_username" class="mt-1 block w-full" :value="old('smtp_username', $account->smtp_username)" required />
                            </div>
                            <div>
                                <x-input-label for="smtp_password" value="Uus parool (tühi = ei muuda)" />
                                <x-text-input id="smtp_password" name="smtp_password" type="password" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="smtp_encryption" value="Krüpteering" />
                                <select id="smtp_encryption" name="smtp_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="tls" @selected(old('smtp_encryption', $account->smtp_encryption)=='tls')>TLS</option>
                                    <option value="ssl" @selected(old('smtp_encryption', $account->smtp_encryption)=='ssl')>SSL</option>
                                    <option value="none" @selected(old('smtp_encryption', $account->smtp_encryption)=='none')>Puudub</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded p-4">
                        <legend class="text-sm font-medium text-gray-700 px-1">IMAP seaded</legend>
                        <div class="grid grid-cols-2 gap-4 mt-2">
                            <div>
                                <x-input-label for="imap_host" value="Host" />
                                <x-text-input id="imap_host" name="imap_host" class="mt-1 block w-full" :value="old('imap_host', $account->imap_host)" />
                            </div>
                            <div>
                                <x-input-label for="imap_port" value="Port" />
                                <x-text-input id="imap_port" name="imap_port" type="number" class="mt-1 block w-full" :value="old('imap_port', $account->imap_port)" />
                            </div>
                            <div>
                                <x-input-label for="imap_username" value="Kasutajanimi" />
                                <x-text-input id="imap_username" name="imap_username" class="mt-1 block w-full" :value="old('imap_username', $account->imap_username)" />
                            </div>
                            <div>
                                <x-input-label for="imap_password" value="Uus parool (tühi = ei muuda)" />
                                <x-text-input id="imap_password" name="imap_password" type="password" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="imap_encryption" value="Krüpteering" />
                                <select id="imap_encryption" name="imap_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="ssl" @selected(old('imap_encryption', $account->imap_encryption)=='ssl')>SSL</option>
                                    <option value="tls" @selected(old('imap_encryption', $account->imap_encryption)=='tls')>TLS</option>
                                    <option value="none" @selected(old('imap_encryption', $account->imap_encryption)=='none')>Puudub</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="daily_limit" value="Päevalimiit" />
                            <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" :value="old('daily_limit', $account->daily_limit)" required />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active)) class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Aktiivne</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Salvesta</x-primary-button>
                        <a href="{{ route('outreach.accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Tühista</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
