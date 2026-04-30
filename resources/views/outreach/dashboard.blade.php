<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Outreach — Ülevaade
            </h2>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('outreach.trigger.process') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                        ▶ Käivita saatmine
                    </button>
                </form>
                <form method="POST" action="{{ route('outreach.trigger.reply-check') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                        ↻ Kontrolli vastuseid
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4">
                @foreach([
                    ['label' => 'Uued vastused',      'value' => $stats['unread_replies'], 'color' => 'purple'],
                    ['label' => 'Kampaaniad',         'value' => $stats['campaigns'],      'color' => 'blue'],
                    ['label' => 'Aktiivsed leadid',   'value' => $stats['active_leads'],   'color' => 'green'],
                    ['label' => 'Vastanud',           'value' => $stats['replied'],        'color' => 'purple'],
                    ['label' => 'Lõpetatud',          'value' => $stats['completed'],      'color' => 'gray'],
                    ['label' => 'Saadetud täna',      'value' => $stats['sent_today'],     'color' => 'indigo'],
                    ['label' => 'Ebaõnnestunud täna', 'value' => $stats['failed_today'],   'color' => 'red'],
                ] as $stat)
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
                    <p class="text-3xl font-bold text-{{ $stat['color'] }}-600 mt-1">{{ $stat['value'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- Quick links --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('outreach.inbox.index') }}" class="bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 flex items-center gap-4">
                    <div class="relative w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 text-xl shrink-0">
                        📬
                        @if($stats['unread_replies'] > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-5 h-5 px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">{{ $stats['unread_replies'] }}</span>
                        @endif
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Inbox</p>
                        <p class="text-sm text-gray-500">
                            @if($stats['unread_replies'] > 0)
                                {{ $stats['unread_replies'] }} uut vastust ootab
                            @else
                                Klientide vastused
                            @endif
                        </p>
                    </div>
                </a>
                <a href="{{ route('outreach.accounts.index') }}" class="bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">✉</div>
                    <div>
                        <p class="font-medium text-gray-900">Postkastid</p>
                        <p class="text-sm text-gray-500">SMTP/IMAP kontod</p>
                    </div>
                </a>
                <a href="{{ route('outreach.campaigns.index') }}" class="bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-xl">📋</div>
                    <div>
                        <p class="font-medium text-gray-900">Kampaaniad</p>
                        <p class="text-sm text-gray-500">Järjestused ja leadid</p>
                    </div>
                </a>
                <a href="{{ route('outreach.campaigns.create') }}" class="bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 text-xl">+</div>
                    <div>
                        <p class="font-medium text-gray-900">Uus kampaania</p>
                        <p class="text-sm text-gray-500">Loo järjestus</p>
                    </div>
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
