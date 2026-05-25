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

                    {{-- HTML signature. Appended automatically to every send
                         (cold campaigns AND manual replies) by OutreachMailer.
                         "Eelvaade kirjas" opens a modal that shows the signature
                         in the context of a realistic sample email body, so the
                         operator sees exactly how it will land in the recipient
                         inbox. --}}
                    <div x-data="{ html: @js(old('signature_html', $account->signature_html ?? '')), showModal: false }">
                        <div class="flex items-center justify-between">
                            <x-input-label for="signature_html" value="HTML jalus (lisatakse iga saadetava kirja lõppu)" />
                            <button type="button" @click="showModal = true"
                                    class="text-xs text-indigo-600 hover:text-indigo-800">
                                Eelvaade kirjas →
                            </button>
                        </div>
                        <textarea id="signature_html" name="signature_html" rows="6"
                                  x-model="html"
                                  placeholder='Näiteks: &lt;br&gt;--&lt;br&gt;&lt;strong&gt;Veiko Teekel&lt;/strong&gt;&lt;br&gt;Web Fight OÜ&lt;br&gt;&lt;a href="https://webfight.ee"&gt;webfight.ee&lt;/a&gt;'
                                  class="mt-1 block w-full font-mono text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('signature_html', $account->signature_html) }}</textarea>
                        <x-input-error :messages="$errors->get('signature_html')" class="mt-1" />
                        <p class="text-xs text-gray-500 mt-1">Lubatud on HTML — <code>&lt;br&gt;</code>, <code>&lt;a href&gt;</code>, <code>&lt;strong&gt;</code> jms. Jäta tühjaks, kui jalust ei taha.</p>

                        {{-- Compact inline preview — quick sanity check while
                             typing. The full email-style preview lives in the
                             modal below. --}}
                        <div class="mt-2">
                            <p class="text-xs font-medium text-gray-600 mb-1">Eelvaade (ainult jalus):</p>
                            <div class="bg-gray-50 border border-gray-200 rounded p-3 text-sm" x-html="html || '<span class=&quot;text-gray-400&quot;>(tühi)</span>'"></div>
                        </div>

                        {{-- Modal: signature rendered inside a sample email
                             body so the operator sees the visual context — how
                             the separator looks, whether colours/fonts collide
                             with normal copy, etc. Backdrop click closes. --}}
                        <div x-show="showModal" x-cloak
                             @keydown.escape.window="showModal = false"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                             @click.self="showModal = false">
                            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                                <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-900">Jaluse eelvaade kirjas</h3>
                                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">×</button>
                                </div>
                                <div class="overflow-y-auto p-5 bg-gray-50">
                                    {{-- Mimic an email-client message bubble so the
                                         operator gets a visual frame around the
                                         signature instead of judging it in vacuum. --}}
                                    <div class="bg-white rounded shadow-sm p-5 text-sm text-gray-800" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; line-height: 1.5;">
                                        <div class="text-xs text-gray-500 mb-3 pb-3 border-b border-gray-100">
                                            <strong>{{ $account->name ?: $account->email }}</strong>
                                            &lt;{{ $account->email }}&gt;<br>
                                            <span class="text-gray-400">Teemarida: Näidiskirja teema</span>
                                        </div>
                                        <p>Tere [eesnimi],</p>
                                        <p class="mt-3">See on näidiskiri, et näidata, kuidas teie jalus välja näeb pärast tegelikku kirja sisu. Tavaliselt on siia kohta paigutatud paar lõiku tekstist.</p>
                                        <p class="mt-3">Aitäh teie aja eest!</p>
                                        <br><br>
                                        <div x-html="html || '<span class=&quot;text-gray-400&quot;>(jalus tühi)</span>'"></div>
                                    </div>
                                </div>
                                <div class="px-5 py-3 border-t border-gray-200 flex justify-end">
                                    <button type="button" @click="showModal = false"
                                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Sulge</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="provider" value="Teenusepakkuja" />
                        <select id="provider" name="provider" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" onchange="toggleRelayFields(this.value)">
                            <option value="smtp" @selected(old('provider', $account->provider)=='smtp')>SMTP (üldine)</option>
                            <option value="gmail" @selected(old('provider', $account->provider)=='gmail')>Gmail</option>
                            <option value="outlook" @selected(old('provider', $account->provider)=='outlook')>Outlook</option>
                            <option value="zone_relay" @selected(old('provider', $account->provider)=='zone_relay')>Zone Relay (HTTP fail veebiserveris)</option>
                        </select>
                    </div>

                    <fieldset id="relay-fields" class="border border-purple-200 bg-purple-50 rounded p-4" style="display: none;">
                        <legend class="text-sm font-medium text-purple-800 px-1">Zone Relay seaded</legend>
                        <div class="space-y-4 mt-2">
                            <div>
                                <x-input-label for="relay_url" value="Relay URL" />
                                <x-text-input id="relay_url" name="relay_url" class="mt-1 block w-full" :value="old('relay_url', $account->relay_url)" placeholder="https://webfight.ee/mail-relay.php" />
                                <p class="text-xs text-gray-600 mt-1">URL kus mail-relay.php fail veebiserveris asub. HTTPS kohustuslik.</p>
                                <x-input-error :messages="$errors->get('relay_url')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="relay_secret" value="Shared secret (tühi = ei muuda)" />
                                <x-text-input id="relay_secret" name="relay_secret" type="password" class="mt-1 block w-full" />
                                <p class="text-xs text-gray-600 mt-1">Vähemalt 16 tähemärki. Sama väärtus mis relay-failis. Tühjaks jätmine säilitab praeguse.</p>
                                <x-input-error :messages="$errors->get('relay_secret')" class="mt-1" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset id="smtp-fields" class="border border-gray-200 rounded p-4">
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

                    <div class="bg-purple-50 border border-purple-200 rounded p-4">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_primary_reply_account" value="1"
                                   @checked(old('is_primary_reply_account', $account->is_primary_reply_account))
                                   class="mt-1 rounded border-gray-300 text-purple-600">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Põhipostkast vastusteks</span>
                                <p class="text-xs text-gray-600 mt-1">
                                    Vastused CRM-i Inbox'ist saadetakse alati sellelt postkastilt (nt veiko@webfight.ee).
                                    Cold-email saatmiseks seda postkasti ei kasutata. Korraga saab olla ainult üks põhipostkast — märkimine teiselt eemaldatakse automaatselt.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Salvesta</x-primary-button>
                        <a href="{{ route('outreach.accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Tühista</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggling display:none alone is not enough — hidden inputs still
        // participate in HTML5 validation, which prevents form submit when a
        // hidden "required" field is blank. Setting `disabled` on the
        // <fieldset> opts the browser out of both submission and validation.
        function toggleRelayFields(provider) {
            const relayFields = document.getElementById('relay-fields');
            const smtpFields  = document.getElementById('smtp-fields');
            const useRelay    = provider === 'zone_relay';

            relayFields.style.display = useRelay ? 'block' : 'none';
            smtpFields.style.display  = useRelay ? 'none'  : 'block';

            relayFields.disabled = ! useRelay;
            smtpFields.disabled  = useRelay;
        }
        document.addEventListener('DOMContentLoaded', () => {
            toggleRelayFields(document.getElementById('provider').value);
        });
    </script>
</x-app-layout>
