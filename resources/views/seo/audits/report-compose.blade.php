<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Saada tehnilise SEO ülevaade</h2>
            <a href="{{ route('seo.audits.show', $audit) }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Tagasi auditisse</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            @if($pageCount > 1)
                <div class="flex gap-2 text-sm">
                    <a href="{{ route('seo.audits.report.compose', $audit) }}"
                       class="px-3 py-1.5 rounded-full border {{ ! $all ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}">Ainult see leht</a>
                    <a href="{{ route('seo.audits.report.compose', ['audit' => $audit, 'all' => 1]) }}"
                       class="px-3 py-1.5 rounded-full border {{ $all ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}">Kõik kliendi lehed ({{ $pageCount }})</a>
                </div>
            @endif

            @if($accounts->isEmpty())
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                    Aktiivset SMTP-postkasti pole — seadista see <a href="{{ route('outreach.accounts.index') }}" class="underline">saatekontode</a> all.
                </div>
            @else
                <form method="POST" action="{{ route('seo.audits.report.send', $audit) }}" class="space-y-4 bg-white shadow-sm rounded-lg p-6">
                    @csrf
                    <input type="hidden" name="all" value="{{ $all ? 1 : 0 }}">

                    <div>
                        <x-input-label value="Saatja" />
                        <select name="account_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($accounts as $a)
                                <option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->name }} &lt;{{ $a->email }}&gt;</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Postkasti jalus lisatakse kirja lõppu.</p>
                    </div>

                    <div>
                        <x-input-label value="Saaja e-mail" />
                        <x-text-input type="email" name="to" class="mt-1 block w-full" required :value="old('to', $to)" />
                        <x-input-error :messages="$errors->get('to')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Teema" />
                        <x-text-input name="subject" class="mt-1 block w-full" required :value="old('subject', $subject)" />
                    </div>

                    <div>
                        <x-input-label value="Kirja sisu" />
                        <textarea name="body" rows="12" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm leading-relaxed">{{ old('body', $body) }}</textarea>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded p-4 flex items-center gap-2 text-sm">
                        <span class="text-lg">📎</span>
                        <span class="font-mono">{{ $pdfName }}</span>
                        <a href="{{ route('seo.audits.report', ['audit' => $audit, 'all' => $all ? 1 : null]) }}" target="_blank" class="ml-auto text-indigo-600 hover:text-indigo-800">Vaata PDF-i →</a>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('seo.audits.show', $audit) }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Tühista</a>
                        <x-primary-button>Saada e-postiga</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
