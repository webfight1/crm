<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Leadid — {{ $campaign->name }}</h2>
            </div>
            <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Kampaania</a>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ettevõte</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Samm</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Järgmine saatmine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Postkast</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Olek</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($leads as $lead)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ $lead->first_name }} {{ $lead->last_name }}</p>
                                <p class="text-sm text-gray-500">{{ $lead->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $lead->company ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ $lead->current_step }}
                                @if($lead->replied) <span class="text-purple-600 ml-1">✓ vastus</span> @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $lead->next_send_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $lead->assignedEmailAccount?->email ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $colors = [
                                        'active'       => 'bg-green-100 text-green-700',
                                        'paused'       => 'bg-yellow-100 text-yellow-700',
                                        'completed'    => 'bg-gray-100 text-gray-600',
                                        'bounced'      => 'bg-red-100 text-red-700',
                                        'unsubscribed' => 'bg-orange-100 text-orange-700',
                                    ];
                                    $labels = [
                                        'active'       => 'Aktiivne',
                                        'paused'       => 'Peatatud',
                                        'completed'    => 'Lõpetatud',
                                        'bounced'      => 'Põrge',
                                        'unsubscribed' => 'Loobunud',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full {{ $colors[$lead->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $labels[$lead->status] ?? $lead->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{-- Quick status change --}}
                                <form method="POST" action="{{ route('outreach.campaigns.leads.update', [$campaign, $lead]) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="next_send_at" value="{{ $lead->next_send_at?->format('Y-m-d\TH:i') }}">
                                    <select name="status" onchange="this.form.submit()"
                                        class="text-xs border-gray-300 rounded py-1 text-gray-700">
                                        @foreach(['active' => 'Aktiivne', 'paused' => 'Peatatud', 'completed' => 'Lõpetatud', 'unsubscribed' => 'Loobunud'] as $val => $label)
                                            <option value="{{ $val }}" @selected($lead->status === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('outreach.campaigns.leads.destroy', [$campaign, $lead]) }}" class="inline ml-2" onsubmit="return confirm('Kustuta lead?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-xs">×</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                Leade pole veel lisatud.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($leads->hasPages())
                    <div class="px-6 py-4 border-t">{{ $leads->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
