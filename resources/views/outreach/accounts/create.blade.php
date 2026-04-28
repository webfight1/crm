<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Lisa postkast</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('outreach.accounts.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Kuvanimi" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="email" value="E-posti aadress" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="provider" value="Teenusepakkuja" />
                        <select id="provider" name="provider" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="smtp" @selected(old('provider')=='smtp')>SMTP (üldine)</option>
                            <option value="gmail" @selected(old('provider')=='gmail')>Gmail</option>
                            <option value="outlook" @selected(old('provider')=='outlook')>Outlook</option>
                        </select>
                    </div>

                    <fieldset class="border border-gray-200 rounded p-4">
                        <legend class="text-sm font-medium text-gray-700 px-1">SMTP seaded</legend>
                        <div class="grid grid-cols-2 gap-4 mt-2">
                            <div>
                                <x-input-label for="smtp_host" value="Host" />
                                <x-text-input id="smtp_host" name="smtp_host" class="mt-1 block w-full" :value="old('smtp_host')" placeholder="smtp.gmail.com" required />
                                <x-input-error :messages="$errors->get('smtp_host')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="smtp_port" value="Port" />
                                <x-text-input id="smtp_port" name="smtp_port" type="number" class="mt-1 block w-full" :value="old('smtp_port', 587)" required />
                                <x-input-error :messages="$errors->get('smtp_port')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="smtp_username" value="Kasutajanimi" />
                                <x-text-input id="smtp_username" name="smtp_username" class="mt-1 block w-full" :value="old('smtp_username')" required />
                            </div>
                            <div>
                                <x-input-label for="smtp_password" value="Parool" />
                                <x-text-input id="smtp_password" name="smtp_password" type="password" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="smtp_encryption" value="Krüpteering" />
                                <select id="smtp_encryption" name="smtp_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="tls" @selected(old('smtp_encryption','tls')=='tls')>TLS (587)</option>
                                    <option value="ssl" @selected(old('smtp_encryption')=='ssl')>SSL (465)</option>
                                    <option value="none" @selected(old('smtp_encryption')=='none')>Puudub</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded p-4">
                        <legend class="text-sm font-medium text-gray-700 px-1">IMAP seaded (vastuste tuvastamiseks)</legend>
                        <div class="grid grid-cols-2 gap-4 mt-2">
                            <div>
                                <x-input-label for="imap_host" value="Host" />
                                <x-text-input id="imap_host" name="imap_host" class="mt-1 block w-full" :value="old('imap_host')" placeholder="imap.gmail.com" />
                            </div>
                            <div>
                                <x-input-label for="imap_port" value="Port" />
                                <x-text-input id="imap_port" name="imap_port" type="number" class="mt-1 block w-full" :value="old('imap_port', 993)" />
                            </div>
                            <div>
                                <x-input-label for="imap_username" value="Kasutajanimi" />
                                <x-text-input id="imap_username" name="imap_username" class="mt-1 block w-full" :value="old('imap_username')" />
                            </div>
                            <div>
                                <x-input-label for="imap_password" value="Parool" />
                                <x-text-input id="imap_password" name="imap_password" type="password" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="imap_encryption" value="Krüpteering" />
                                <select id="imap_encryption" name="imap_encryption" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="ssl" @selected(old('imap_encryption','ssl')=='ssl')>SSL (993)</option>
                                    <option value="tls" @selected(old('imap_encryption')=='tls')>TLS</option>
                                    <option value="none" @selected(old('imap_encryption')=='none')>Puudub</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="daily_limit" value="Päevalimiit (e-kirja)" />
                            <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" :value="old('daily_limit', 50)" required />
                            <x-input-error :messages="$errors->get('daily_limit')" class="mt-1" />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Aktiivne</span>
                            </label>
                        </div>
                    </div>

                    <div class="bg-purple-50 border border-purple-200 rounded p-4">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_primary_reply_account" value="1"
                                   @checked(old('is_primary_reply_account', false))
                                   class="mt-1 rounded border-gray-300 text-purple-600">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Põhipostkast vastusteks</span>
                                <p class="text-xs text-gray-600 mt-1">
                                    Vastused CRM-i Inbox'ist saadetakse alati sellelt postkastilt (nt veiko@webfight.ee).
                                    Cold-email saatmiseks seda postkasti ei kasutata.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Lisa postkast</x-primary-button>
                        <a href="{{ route('outreach.accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Tühista</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
