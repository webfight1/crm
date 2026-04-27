<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Inbox</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('outreach.dashboard') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Töölaud</a>
                <form method="POST" action="{{ route('outreach.trigger.reply-check') }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                        ↻ Kontrolli vastuseid
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <form method="GET" class="bg-white shadow-sm rounded-lg p-4">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Otsi email, nime või subjekti järgi…"
                           class="flex-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Otsi</button>
                    @if($search !== '')
                        <a href="{{ route('outreach.inbox.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Tühjenda</a>
                    @endif
                </div>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontakt</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kampaaniad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vastuseid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Viimane vastus</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($threads as $thread)
                            @php
                                $encoded = rtrim(strtr(base64_encode($thread->group_email), '+/', '-_'), '=');
                                $name    = trim(($thread->lead_first_name ?? '') . ' ' . ($thread->lead_last_name ?? ''));
                                if ($name === '') { $name = $thread->display_name ?: '—'; }
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <a href="{{ route('outreach.inbox.thread', $encoded) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $name }}</a>
                                    @if($thread->lead_company)
                                        <div class="text-xs text-gray-500 mt-0.5">{{ $thread->lead_company }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $thread->group_email }}</td>
                                <td class="px-6 py-4">
                                    @forelse($thread->campaigns as $campaignName)
                                        <span class="inline-block px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded mr-1 mb-1">{{ $campaignName }}</span>
                                    @empty
                                        <span class="text-xs text-gray-400">—</span>
                                    @endforelse
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $thread->reply_count }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($thread->last_received_at)->diffForHumans() }}
                                    <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($thread->last_received_at)->format('d.m.Y H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    @if($search !== '')
                                        Otsingule ei leitud vasteid.
                                    @else
                                        Vastuseid pole veel. Cron'i tagant tõmmatakse uued vastused automaatselt.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($threads->hasPages())
                    <div class="px-6 py-4 border-t">{{ $threads->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
