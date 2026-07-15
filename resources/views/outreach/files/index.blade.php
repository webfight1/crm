<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Failid</h2>
            <a href="{{ route('outreach.dashboard') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Töölaud</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                Lae fail (nt PDF-pakkumine) üles ja saad avaliku lingi. Kopeeri link ja pane
                kampaania kirja sisse tavalise lingina — nii jäävad kirjad väikesed ja
                deliverability paremaks kui raske manusega.
            </p>

            {{-- Upload --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Lae fail üles</h3>
                <form method="POST" action="{{ route('outreach.files.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="file" required
                           class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:cursor-pointer">
                    <x-primary-button>Lae üles</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                <p class="text-xs text-gray-400 mt-2">Lubatud: PDF, pildid, Word/Excel/PPT, CSV, TXT, ZIP · max 20 MB</p>

                @if(session('uploaded_url'))
                    <div class="mt-4 bg-indigo-50 border border-indigo-200 rounded px-3 py-2 flex items-center gap-2">
                        <input type="text" readonly value="{{ session('uploaded_url') }}"
                               class="flex-1 bg-white border-gray-300 rounded text-sm text-gray-700" onclick="this.select()">
                        <button type="button" class="copy-link text-xs bg-indigo-600 text-white px-3 py-2 rounded hover:bg-indigo-700 whitespace-nowrap"
                                data-url="{{ session('uploaded_url') }}">Kopeeri link</button>
                    </div>
                @endif
            </div>

            {{-- File list --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Üleslaetud failid</h3>

                @forelse($files as $f)
                    <div class="flex items-center gap-3 border-b border-gray-100 py-2 last:border-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-800 truncate">📎 {{ $f['name'] }}
                                <span class="text-gray-400 text-xs">({{ number_format($f['size'] / 1024, 0) }} KB)</span>
                            </p>
                            <a href="{{ $f['url'] }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 break-all">{{ $f['url'] }}</a>
                        </div>
                        <button type="button" class="copy-link shrink-0 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded"
                                data-url="{{ $f['url'] }}">Kopeeri</button>
                        <form method="POST" action="{{ route('outreach.files.destroy') }}" class="shrink-0"
                              onsubmit="return confirm('Kustuta fail? Kirjades olevad lingid lakkavad töötamast.')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="name" value="{{ $f['name'] }}">
                            <button class="text-xs text-red-600 hover:text-red-800">Kustuta</button>
                        </form>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Faile pole veel üles laetud.</p>
                @endforelse
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.copy-link');
            if (!btn) return;
            const url = btn.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                const old = btn.textContent;
                btn.textContent = 'Kopeeritud!';
                setTimeout(() => { btn.textContent = old; }, 1500);
            });
        });
    </script>
</x-app-layout>
