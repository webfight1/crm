<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kalender') }}
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('calendar.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Lisa sündmus') }}
                </a>
                <a href="{{ route('calendar.feed') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('iCal Feed') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid grid-cols-7 gap-4">
                        <!-- Calendar header -->
                        <div class="text-center font-medium">E</div>
                        <div class="text-center font-medium">T</div>
                        <div class="text-center font-medium">K</div>
                        <div class="text-center font-medium">N</div>
                        <div class="text-center font-medium">R</div>
                        <div class="text-center font-medium">L</div>
                        <div class="text-center font-medium">P</div>

                        <!-- Calendar days -->
                        @php
                            $start = now()->startOfMonth()->startOfWeek();
                            $end = now()->endOfMonth()->endOfWeek();
                            $today = now()->startOfDay();
                        @endphp

                        @while($start <= $end)
                            <div class="min-h-[120px] p-2 border rounded-lg {{ $start->format('m') != now()->format('m') ? 'bg-gray-50' : '' }} {{ $start->format('Y-m-d') === $today->format('Y-m-d') ? 'bg-blue-50 border-blue-200' : '' }}">
                                <div class="text-sm {{ $start->format('m') != now()->format('m') ? 'text-gray-400' : 'text-gray-700' }}">
                                    {{ $start->format('j') }}
                                </div>
                                
                                <!-- Events for this day -->
                                <div class="space-y-1 mt-1">
                                    @foreach($events->filter(function($event) use ($start) {
                                        return $event->start_time->format('Y-m-d') === $start->format('Y-m-d');
                                    }) as $event)
                                        <div class="text-xs p-1 rounded {{ $event->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $event->start_time->format('H:i') }} - {{ $event->title }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @php $start->addDay(); @endphp
                        @endwhile
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
