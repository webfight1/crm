<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if(request()->has('favorite'))
                    {{ __('Tärniga ülesanded') }}
                @else
                    {{ __('Ülesanded') }}
                @endif
            </h2>
            <a href="{{ route('tasks.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Uus Ülesanne
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

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <!-- User Filter -->
            <div class="mb-6">
                <form method="GET" action="{{ route('tasks.index') }}" class="flex items-center space-x-4">
                    <div class="flex-grow max-w-xs">
                        <select name="user_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" onchange="this.form.submit()">
                            <option value="all" {{ request('user_id') === 'all' ? 'selected' : '' }}>Kõik ülesanded</option>
                            <option value="mine" {{ request('user_id') === 'mine' ? 'selected' : '' }}>Minu loodud</option>
                            <option value="assigned" {{ request('user_id') === 'assigned' ? 'selected' : '' }}>Minule määratud</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} loodud
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Ootel</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $tasks->where('status', 'pending')->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 1.414L10.586 9.5 9.293 8.207a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 00-1.414-1.414L11 9.586z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pooleli</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $tasks->where('status', 'in_progress')->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Lõpetatud</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $tasks->where('status', 'completed')->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Kokku</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $tasks->count() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($tasks->count() > 0)
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 space-y-4 md:space-y-0">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Ülesannete loetelu</h3>
                                <p class="text-sm text-gray-500">Filtreeri ülesandeid staatuse järgi.</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="task-status-filter" class="text-sm font-medium text-gray-700">Staatus:</label>
                                <select id="task-status-filter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Kõik</option>
                                    <option value="pending">Ootel</option>
                                    <option value="in_progress">Pooleli</option>
                                    <option value="completed">Lõpetatud</option>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table id="tasks-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8"></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ülesanne</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vastutaja</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tehing</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kulunud aeg</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ettevõte</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioriteet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staatus</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tähtaeg</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toimingud</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($tasks as $task)
                                        <tr>
                                            <td class="px-2 py-4 whitespace-nowrap text-center">
                                                <button onclick="toggleFavorite({{ $task->id }})" class="favorite-btn focus:outline-none hover:scale-110 transition-transform" data-task-id="{{ $task->id }}" data-is-favorite="{{ $task->is_favorite ? 'true' : 'false' }}">
                                                    <svg class="w-5 h-5 transition-all" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="#facc15" stroke-width="2" fill="{{ $task->is_favorite ? '#facc15' : 'none' }}">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                    </svg>
                                                </button>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <a href="{{ route('tasks.show', $task) }}" class="text-blue-600 hover:text-blue-900">
                                                            {{ $task->title }}
                                                        </a>
                                                    </div>
                                                    @if($task->description)
                                                        <div class="text-sm text-gray-500">
                                                            {{ Str::limit(strip_tags($task->description), 60) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($task->assignee)
                                                    {{ $task->assignee->name }}
                                                @else
                                                    <span class="text-gray-400">Määramata</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($task->deal)
                                                    <a href="{{ route('deals.show', $task->deal) }}" class="text-blue-600 hover:text-blue-800">
                                                        {{ Str::limit($task->deal->title, 30) }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @php
                                                    $total = (float) ($task->time_entries_sum_duration ?? 0);
                                                    $hours = floor($total);
                                                    $minutes = round(($total - $hours) * 60);
                                                @endphp
                                                @if($total > 0)
                                                    <span title="Taimeri abil mõõdetud aeg">{{ $hours }}h {{ $minutes }}min</span>
                                                @else
                                                    <span class="text-gray-400" title="Aega pole veel mõõdetud">0h</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @php
                                                    $companyName = null;
                                                    if ($task->deal && $task->deal->company) {
                                                        $companyName = $task->deal->company->name;
                                                    } elseif (isset($task->company) && $task->company) {
                                                        $companyName = $task->company->name;
                                                    }
                                                @endphp
                                                @if($companyName)
                                                    {{ $companyName }}
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($task->priority === 'high') bg-red-100 text-red-800
                                                    @elseif($task->priority === 'medium') bg-yellow-100 text-yellow-800
                                                    @else bg-green-100 text-green-800 @endif">
                                                    @if($task->priority === 'high') Kõrge
                                                    @elseif($task->priority === 'medium') Keskmine
                                                    @else Madal @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($task->status === 'completed') bg-green-100 text-green-800
                                                    @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                                    @else bg-yellow-100 text-yellow-800 @endif">
                                                    @if($task->status === 'completed') Lõpetatud
                                                    @elseif($task->status === 'in_progress') Pooleli
                                                    @else Ootel @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($task->due_date)
                                                    <span class="@if($task->due_date->isPast() && $task->status !== 'completed') text-red-600 @endif" title="{{ $task->due_date->format('d.m.Y') }}">
                                                        @if($task->due_date->isToday())
                                                            Täna
                                                        @elseif($task->due_date->isTomorrow())
                                                            Homme
                                                        @elseif($task->due_date->isAfter(now()) && $task->due_date->isBefore(now()->addDays(2)))
                                                            Ülehomme
                                                        @elseif($task->due_date->isAfter(now()) && $task->due_date->isBefore(now()->addDays(7)))
                                                            {{ $task->due_date->locale('et')->dayName }}
                                                        @else
                                                            {{ $task->due_date->locale('et')->dayName }}, {{ $task->due_date->format('d.m.Y') }}
                                                        @endif
                                                    </span>
                                                    <span class="text-sm ml-2 @if($task->due_date->isPast()) text-red-600 @else text-gray-500 @endif">
                                                        @php
                                                            $diff = now()->startOfDay()->diffInDays($task->due_date->startOfDay(), false);
                                                        @endphp
                                                        @if($diff < 0)
                                                            -{{ abs($diff) }}p
                                                        @else
                                                            {{ $diff }}p
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                           
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                @if(Auth::id() === $task->user_id || Auth::id() === $task->assignee_id)
                                                    <a href="{{ route('tasks.edit', $task) }}" class="text-green-600 hover:text-green-900">
                                                        Muuda
                                                    </a>
                                                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900" 
                                                            onclick="return confirm('Kas oled kindel, et tahad selle ülesande kustutada?')">
                                                            Kustuta
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6"></div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Ülesandeid pole</h3>
                            <p class="mt-1 text-sm text-gray-500">Alusta oma esimese ülesande loomisega.</p>
                            <div class="mt-6">
                                <a href="{{ route('tasks.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    Uus Ülesanne
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
                const tableElement = document.getElementById('tasks-table');
                if (!tableElement || typeof window.jQuery === 'undefined') {
                    return;
                }

                const $ = window.jQuery;
                const tasksTable = $(tableElement).DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/et.json'
                    },
                    pageLength: 500,
                    order: [[8, 'asc']], // Sort by due date (tähtaeg) column
                    columnDefs: [
                        { orderable: false, targets: 0 } // Disable sorting on star column
                    ],
                    responsive: true
                });

                const statusLabelMap = {
                    'pending': 'Ootel',
                    'in_progress': 'Pooleli',
                    'completed': 'Lõpetatud'
                };

                const statusFilter = document.getElementById('task-status-filter');
                statusFilter?.addEventListener('change', function (event) {
                    const value = event.target.value;
                    const term = value ? (statusLabelMap[value] || '') : '';
                    // Status column is the 8th column (0-based index 7) - updated because of star column
                    tasksTable.column(7).search(term, false, false).draw();
                });
            });

            // Toggle favorite function
            function toggleFavorite(taskId) {
                const button = document.querySelector(`button[data-task-id="${taskId}"]`);
                const svg = button.querySelector('svg');
                
                fetch(`/tasks/${taskId}/toggle-favorite`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the fill attribute directly
                        svg.setAttribute('fill', data.is_favorite ? '#facc15' : 'none');
                        // Update data attribute
                        button.setAttribute('data-is-favorite', data.is_favorite ? 'true' : 'false');
                    }
                })
                .catch(error => {
                    console.error('Error toggling favorite:', error);
                });
            }
        </script>
    @endpush
</x-app-layout>
