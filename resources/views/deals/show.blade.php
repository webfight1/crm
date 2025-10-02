<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $deal->title }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('deals.edit', $deal) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Muuda
                </a>
                <a href="{{ route('deals.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Tagasi
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        /* Scoped styles for rich text content */
        .rte-content ul { list-style: disc; padding-left: 1.25rem; margin-left: 0.5rem; }
        .rte-content ol { list-style: decimal; padding-left: 1.25rem; margin-left: 0.5rem; }
        .rte-content li { margin-left: 0.75rem; }
        .rte-content a { color: #2563eb; text-decoration: none; }
        .rte-content a:hover { text-decoration: underline; }
    </style>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Pealkiri</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->title }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Väärtus</dt>
                            <dd class="mt-1 text-sm text-gray-900">€{{ number_format($deal->value, 2) }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Etapp</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($deal->stage === 'closed_won') bg-green-100 text-green-800
                                    @elseif($deal->stage === 'closed_lost') bg-red-100 text-red-800
                                    @elseif($deal->stage === 'negotiation') bg-blue-100 text-blue-800
                                    @elseif($deal->stage === 'proposal') bg-yellow-100 text-yellow-800
                                    @elseif($deal->stage === 'qualified') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    @if($deal->stage === 'lead') Potentsiaalne klient
                                    @elseif($deal->stage === 'qualified') Kvalifitseeritud
                                    @elseif($deal->stage === 'proposal') Pakkumine
                                    @elseif($deal->stage === 'negotiation') Läbirääkimised
                                    @elseif($deal->stage === 'closed_won') Võidetud
                                    @elseif($deal->stage === 'closed_lost') Kaotatud
                                    @endif
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tõenäosus</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->probability }}%</dd>
                        </div>

                        @if($deal->expected_close_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Eeldatav sulgemise kuupäev</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->expected_close_date->format('d.m.Y') }}</dd>
                        </div>
                        @endif

                        @if($deal->actual_close_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tegelik sulgemise kuupäev</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->actual_close_date->format('d.m.Y') }}</dd>
                        </div>
                        @endif

                        @if($deal->customer)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Klient</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('customers.show', $deal->customer) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $deal->customer->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($deal->company)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('companies.show', $deal->company) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $deal->company->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($deal->contact)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Kontakt</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('contacts.show', $deal->contact) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $deal->contact->first_name }} {{ $deal->contact->last_name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->created_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uuendatud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $deal->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        @if($deal->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Kirjeldus</dt>
                            <dd class="prose prose-sm max-w-none mt-1 text-gray-900 rte-content">{!! $deal->description !!}</dd>
                        </div>
                        @endif

                        <!-- Tasks Summary -->
                        <div class="md:col-span-2 mt-6">
                            <dt class="text-sm font-medium text-gray-500 mb-4">Seotud ülesanded</dt>
                            @if($deal->tasks->count() > 0)
                                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                                    <ul class="divide-y divide-gray-200">
                                        @php
                                            $totalTime = 0;
                                            $totalCost = 0;
                                        @endphp
                                        @foreach($deal->tasks as $task)
                                            @php
                                                $taskTrackedTime = $task->timeEntries->sum('duration') ?? 0;
                                                $taskCost = $taskTrackedTime * ($task->price ?? 0);
                                                $totalTime += $taskTrackedTime;
                                                $totalCost += $taskCost;
                                            @endphp
                                            <li>
                                                <div class="px-4 py-4 sm:px-6">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex-1">
                                                            <div class="flex items-center justify-between">
                                                                <p class="text-sm font-medium text-indigo-600 truncate">
                                                                    <a href="{{ route('tasks.show', $task) }}" class="hover:text-indigo-900">
                                                                        {{ $task->title }}
                                                                    </a>
                                                                </p>
                                                                <div class="ml-2 flex-shrink-0 flex">
                                                                    <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                                        @if($task->status === 'completed') bg-green-100 text-green-800
                                                                        @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                                                        @else bg-yellow-100 text-yellow-800 @endif">
                                                                        @if($task->status === 'completed') Lõpetatud
                                                                        @elseif($task->status === 'in_progress') Pooleli
                                                                        @else Ootel @endif
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 sm:flex sm:justify-between">
                                                                <div class="sm:flex">
                                                                    <div class="space-y-2">
                                                                        @foreach($task->timeEntries as $timeEntry)
                                                                        <div class="flex items-center justify-between text-sm text-gray-500">
                                                                            <p class="flex items-center">
                                                                                <!-- Timer Clock Icon -->
                                                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                                                </svg>
                                                                                {{ $timeEntry->start_time->format('d.m H:i') }}: {{ floor($timeEntry->duration) }}h {{ round(($timeEntry->duration - floor($timeEntry->duration)) * 60) }}min
                                                                            </p>
                                                                            <a href="{{ route('time-entries.edit', $timeEntry) }}" class="text-indigo-600 hover:text-indigo-900 ml-2">
                                                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                                </svg>
                                                                            </a>
                                                                        </div>
                                                                        @endforeach

                                                                        @if($taskTrackedTime > 0)
                                                                        <p class="flex items-center text-sm font-medium text-gray-700 pt-2 border-t border-gray-200">
                                                                            <!-- Total Clock Icon -->
                                                                            <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                                                                            </svg>
                                                                            Kokku: {{ floor($taskTrackedTime) }}h {{ round(($taskTrackedTime - floor($taskTrackedTime)) * 60) }}min
                                                                        </p>
                                                                        @endif
                                                                    </div>
                                                                    <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0 sm:ml-6">
                                                                        <!-- Currency Icon -->
                                                                        <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                                                        </svg>
                                                                        @if($task->price)
                                                                            {{ number_format($taskCost, 2) }}€ ({{ number_format($task->price, 2) }}€/h)
                                                                        @else
                                                                            Hind määramata
                                                                        @endif
                                                                    </p>
                                                                </div>
                                                                @if($task->due_date)
                                                                <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                                                    <!-- Calendar Icon -->
                                                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                                    </svg>
                                                                    <p class="@if($task->due_date->isPast() && $task->status !== 'completed') text-red-600 font-medium @endif">
                                                                        {{ $task->due_date->format('d.m.Y') }}
                                                                    </p>
                                                                </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <!-- Tasks Summary Footer -->
                                    <div class="bg-gray-50 px-4 py-4 sm:px-6 border-t border-gray-200">
                                        <div class="flex justify-between text-sm">
                                            <div class="font-medium text-gray-500">
                                                Kokku ajakulu: <span class="text-gray-900">{{ floor($totalTime) }}h {{ round(($totalTime - floor($totalTime)) * 60) }}min</span>
                                            </div>
                                            <div class="font-medium text-gray-500">
                                                Kogumaksumus: <span class="text-gray-900">{{ number_format($totalCost, 2) }}€</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Ülesanded puuduvad</p>
                            @endif
                        </div>

                        @if($deal->notes)
                        <div class="md:col-span-2 mt-6">
                            <dt class="text-sm font-medium text-gray-500">Märkused</dt>
                            <dd class="prose prose-sm max-w-none mt-1 text-gray-900 rte-content">{!! $deal->notes !!}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
