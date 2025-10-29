<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Tehingud') }}
            </h2>
            <a href="{{ route('deals.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Lisa tehing
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($deals->count() > 0)
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 space-y-4 md:space-y-0">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Tehingute loetelu</h3>
                                <p class="text-sm text-gray-500">Kasuta filtrit, et näha ainult kindlas staadiumis tehinguid.</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="deal-stage-filter" class="text-sm font-medium text-gray-700">Staatus:</label>
                                <select id="deal-stage-filter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Kõik</option>
                                    <option value="lead">Potentsiaalne</option>
                                    <option value="qualified">Kvalifitseeritud</option>
                                    <option value="proposal">Pakkumine</option>
                                    <option value="negotiation">Läbirääkimised</option>
                                    <option value="closed_won">Võidetud</option>
                                    <option value="closed_lost">Kaotatud</option>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table id="deals-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tehing
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Klient/Ettevõte
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Väärtus
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Staatus
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tõenäosus
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Lõpukuupäev
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Toimingud
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($deals as $deal)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $deal->title }}
                                                    </div>
                                                    @if($deal->description)
                                                        <div class="text-sm text-gray-500">
                                                            {{ Str::limit(strip_tags($deal->description), 50) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($deal->customer)
                                                    <div class="font-medium">{{ $deal->customer->full_name }}</div>
                                                @endif
                                                @if($deal->company)
                                                    <div class="text-gray-500">{{ $deal->company->name }}</div>
                                                @endif
                                                @if(!$deal->customer && !$deal->company)
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                €{{ number_format($deal->value, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($deal->stage === 'closed_won') bg-green-100 text-green-800
                                                    @elseif($deal->stage === 'closed_lost') bg-red-100 text-red-800
                                                    @elseif($deal->stage === 'negotiation') bg-yellow-100 text-yellow-800
                                                    @elseif($deal->stage === 'proposal') bg-blue-100 text-blue-800
                                                    @elseif($deal->stage === 'qualified') bg-purple-100 text-purple-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    @if($deal->stage === 'lead')
                                                        Potentsiaalne
                                                    @elseif($deal->stage === 'qualified')
                                                        Kvalifitseeritud
                                                    @elseif($deal->stage === 'proposal')
                                                        Pakkumine
                                                    @elseif($deal->stage === 'negotiation')
                                                        Läbirääkimised
                                                    @elseif($deal->stage === 'closed_won')
                                                        Võidetud
                                                    @elseif($deal->stage === 'closed_lost')
                                                        Kaotatud
                                                    @else
                                                        {{ ucfirst(str_replace('_', ' ', $deal->stage)) }}
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $deal->probability }}%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($deal->expected_close_date)
                                                    {{ $deal->expected_close_date->format('d.m.Y') }}
                                                @elseif($deal->actual_close_date)
                                                    {{ $deal->actual_close_date->format('d.m.Y') }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('deals.show', $deal) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Vaata</a>
                                                <a href="{{ route('deals.edit', $deal) }}" class="text-blue-600 hover:text-blue-900 mr-3">Muuda</a>
                                                <form action="{{ route('deals.destroy', $deal) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Kas oled kindel?')">Kustuta</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $deals->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Tehinguid pole</h3>
                            <p class="mt-1 text-sm text-gray-500">Alusta oma esimese tehingu lisamisega.</p>
                            <div class="mt-6">
                                <a href="{{ route('deals.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Lisa tehing
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tableElement = document.getElementById('deals-table');
                if (!tableElement || typeof window.jQuery === 'undefined') {
                    return;
                }

                const $ = window.jQuery;
                const dealsTable = $(tableElement).DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/et.json'
                    },
                    pageLength: 15,
                    order: [[0, 'asc']],
                    responsive: true
                });

                const stageLabelMap = {
                    'lead': 'Potentsiaalne',
                    'qualified': 'Kvalifitseeritud',
                    'proposal': 'Pakkumine',
                    'negotiation': 'Läbirääkimised',
                    'closed_won': 'Võidetud',
                    'closed_lost': 'Kaotatud'
                };

                const stageFilter = document.getElementById('deal-stage-filter');
                stageFilter?.addEventListener('change', function (event) {
                    const value = event.target.value;
                    const term = value ? stageLabelMap[value] || '' : '';
                    dealsTable.column(3).search(term, false, false).draw();
                });
            });
        </script>
    @endpush
</x-app-layout>
