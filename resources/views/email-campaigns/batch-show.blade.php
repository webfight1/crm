<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $batch->name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">{{ $batch->subject }}</p>
            </div>
            <a href="{{ route('email-campaigns.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Tagasi
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Batch Info -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">CSV Fail</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $batch->csv_filename }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Kokku E-maile</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $batch->total_emails }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Saadetud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $batch->sent_emails }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ebaõnnestunud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $batch->failed_emails }}</dd>
                        </div>
                    </div>

                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Progress</dt>
                        <dd class="mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-4">
                                <div class="bg-blue-600 h-4 rounded-full" style="width: {{ $batch->progress_percentage }}%"></div>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                {{ $batch->progress_percentage }}% lõpetatud
                            </div>
                        </dd>
                    </div>

                    @if($batch->status === 'pending')
                        <div class="mt-6">
                            <form method="POST" action="{{ route('email-campaigns.start-sending') }}" class="inline">
                                @csrf
                                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                    Alusta Saatmist
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Individual Campaigns -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Üksikud E-mailid</h3>
                    
                    @if($campaigns->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saaja</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ettevõte</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sektor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefon</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staatus</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saadetud</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toimingud</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($campaigns as $campaign)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $campaign->recipient_email }}
                                                    </div>
                                                    @if($campaign->recipient_name)
                                                        <div class="text-sm text-gray-500">
                                                            {{ $campaign->recipient_name }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $campaign->company_name ?: 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $campaign->sector ?: 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $campaign->phone ?: 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($campaign->status === 'sent') bg-green-100 text-green-800
                                                    @elseif($campaign->status === 'failed') bg-red-100 text-red-800
                                                    @else bg-yellow-100 text-yellow-800 @endif">
                                                    @if($campaign->status === 'sent') Saadetud
                                                    @elseif($campaign->status === 'failed') Ebaõnnestus
                                                    @else Ootel @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $campaign->sent_at ? $campaign->sent_at->format('d.m.Y H:i') : '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('email-campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    Vaata
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $campaigns->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">Selles kampaanias pole e-maile.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
