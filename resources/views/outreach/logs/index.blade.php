<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Saatmislogi — {{ $campaign->name }}</h2>
            <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Kampaania</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Samm</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teema</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Postkast</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Olek</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aeg</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($logs as $log)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $log->to_email }}</p>
                                @if($log->lead)
                                    <p class="text-xs text-gray-500">{{ $log->lead->first_name }} {{ $log->lead->last_name }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">#{{ $log->step_order }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">{{ $log->subject }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $log->from_email }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusClasses = [
                                        'sent'    => 'bg-green-100 text-green-700',
                                        'failed'  => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'skipped' => 'bg-gray-100 text-gray-600',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses[$log->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $log->status }}
                                </span>
                                @if($log->error_message)
                                    <p class="text-xs text-red-500 mt-1 max-w-xs truncate" title="{{ $log->error_message }}">
                                        {{ Str::limit($log->error_message, 60) }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ ($log->sent_at ?? $log->created_at)?->format('d.m.Y H:i') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">Logisid pole.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($logs->hasPages())
                    <div class="px-6 py-4 border-t">{{ $logs->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
