<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Kampaaniad</h2>
            <a href="{{ route('outreach.campaigns.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                + Uus kampaania
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kampaania</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leadid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Päevalimiit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Olek</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loodud</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($campaigns as $campaign)
                        <tr>
                            <td class="px-6 py-4">
                                <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                    {{ $campaign->name }}
                                </a>
                                @if($campaign->description)
                                    <p class="text-sm text-gray-500 mt-0.5">{{ Str::limit($campaign->description, 60) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $campaign->leads_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $campaign->daily_limit ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($campaign->is_active)
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">Aktiivne</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">Peatatud</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $campaign->created_at->format('d.m.Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-900 text-sm mr-3">Ava</a>
                                <form method="POST" action="{{ route('outreach.campaigns.destroy', $campaign) }}" class="inline" onsubmit="return confirm('Kustuta kampaania koos kõigi leadidega?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Kustuta</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                Kampaaniaid pole. <a href="{{ route('outreach.campaigns.create') }}" class="text-indigo-600 hover:underline">Loo esimene kampaania</a>.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($campaigns->hasPages())
                    <div class="px-6 py-4 border-t">{{ $campaigns->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
