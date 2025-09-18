<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $task->title }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('tasks.edit', $task) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Muuda
                </a>
                <a href="{{ route('tasks.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Tagasi
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Pealkiri</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->title }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Prioriteet</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($task->priority === 'high') bg-red-100 text-red-800
                                    @elseif($task->priority === 'medium') bg-yellow-100 text-yellow-800
                                    @else bg-green-100 text-green-800 @endif">
                                    @if($task->priority === 'high') Kõrge
                                    @elseif($task->priority === 'medium') Keskmine
                                    @else Madal @endif
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Staatus</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($task->status === 'completed') bg-green-100 text-green-800
                                    @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    @if($task->status === 'completed') Lõpetatud
                                    @elseif($task->status === 'in_progress') Pooleli
                                    @else Ootel @endif
                                </span>
                            </dd>
                        </div>

                        @if($task->due_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tähtaeg</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="@if($task->due_date->isPast() && $task->status !== 'completed') text-red-600 font-medium @endif">
                                    {{ $task->due_date->format('d.m.Y') }}
                                    @if($task->due_date->isPast() && $task->status !== 'completed')
                                        (Tähtaeg ületatud)
                                    @endif
                                </span>
                            </dd>
                        </div>
                        @endif

                        @if($task->customer)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Klient</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('customers.show', $task->customer) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $task->customer->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($task->company)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('companies.show', $task->company) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $task->company->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->created_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uuendatud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        @if($task->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Kirjeldus</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $task->description }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
