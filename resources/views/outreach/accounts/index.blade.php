<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Postkastid</h2>
            <a href="{{ route('outreach.accounts.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                + Lisa postkast
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nimi / E-post</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SMTP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Päevalimiit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Täna saadetud</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tervis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Olek</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($accounts as $account)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">
                                    {{ $account->name }}
                                    @if($account->is_primary_reply_account)
                                        <span class="ml-2 inline-block px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded">↪ Põhipostkast vastusteks</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500">{{ $account->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $account->smtp_host }}:{{ $account->smtp_port }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $account->daily_limit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ $account->sent_today }}
                                <span class="text-gray-400">/ {{ $account->daily_limit }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($account->consecutive_failures >= \App\Outreach\Models\OutreachEmailAccount::FAILURE_THRESHOLD)
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">Vigane ({{ $account->consecutive_failures }})</span>
                                @elseif($account->consecutive_failures > 0)
                                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-700 rounded-full">{{ $account->consecutive_failures }} viga</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">OK</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($account->is_active)
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">Aktiivne</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">Peatatud</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('outreach.accounts.edit', $account) }}" class="text-indigo-600 hover:text-indigo-900 text-sm mr-3">Muuda</a>
                                <form method="POST" action="{{ route('outreach.accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Kustuta postkast?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Kustuta</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                Postkaste pole. <a href="{{ route('outreach.accounts.create') }}" class="text-indigo-600 hover:underline">Lisa esimene postkast</a>.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($accounts->hasPages())
                    <div class="px-6 py-4 border-t">{{ $accounts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
