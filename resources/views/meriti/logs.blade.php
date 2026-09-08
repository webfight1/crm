<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Meeldetuletuste ajalugu') }}
            </h2>
            <a href="{{ route('meriti.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Tagasi') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($logs->isEmpty())
                    <div class="px-6 py-8 text-center text-gray-500">{{ __('Ühtegi meeldetuletust pole veel saadetud.') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Aeg') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Klient') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('E-post') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500">{{ __('Aste') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Päevi üle') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Summa') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Arved') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500">{{ __('Staatus') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($logs as $log)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ ($log->sent_at ?? $log->created_at)->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-3 text-gray-900">{{ $log->customer_name ?: '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $log->email ?: '—' }}</td>
                                        <td class="px-4 py-3 text-center">{{ $log->level }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $log->overdue_days }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($log->total_unpaid, 2, ',', ' ') }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ implode(', ', $log->invoice_numbers ?? []) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($log->status === 'sent')
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('saadetud') }}</span>
                                            @elseif($log->status === 'failed')
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" title="{{ $log->error }}">{{ __('viga') }}</span>
                                            @else
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700" title="{{ $log->error }}">{{ __('vahele') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4">{{ $logs->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
