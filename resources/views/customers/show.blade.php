<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $customer->full_name }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('customers.edit', $customer) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Muuda
                </a>
                <a href="{{ route('customers.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Tagasi nimekirja
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Customer Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Kliendi andmed</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nimi</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $customer->full_name }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">E-post</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="mailto:{{ $customer->email }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $customer->email }}
                                        </a>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Telefon</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($customer->phone)
                                            <a href="tel:{{ $customer->phone }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $customer->phone }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($customer->company)
                                            <a href="{{ route('companies.show', $customer->company) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $customer->company->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Staatus</dt>
                                    <dd class="mt-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($customer->status === 'active') bg-green-100 text-green-800
                                            @elseif($customer->status === 'inactive') bg-red-100 text-red-800
                                            @else bg-yellow-100 text-yellow-800 @endif">
                                            @if($customer->status === 'active') Aktiivne
                                            @elseif($customer->status === 'inactive') Mitteaktiivne
                                            @else {{ ucfirst($customer->status) }} @endif
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Sünnikuupäev</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $customer->date_of_birth ? $customer->date_of_birth->format('d.m.Y') : 'N/A' }}
                                    </dd>
                                </div>

                                @if($customer->address || $customer->city || $customer->state || $customer->postal_code || $customer->country)
                                    <div class="md:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">Aadress</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            @if($customer->address)
                                                {{ $customer->address }}<br>
                                            @endif
                                            @if($customer->city || $customer->state || $customer->postal_code)
                                                {{ $customer->city }}{{ $customer->city && ($customer->state || $customer->postal_code) ? ', ' : '' }}
                                                {{ $customer->state }} {{ $customer->postal_code }}<br>
                                            @endif
                                            @if($customer->country)
                                                {{ $customer->country }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif

                                @if($customer->notes)
                                    <div class="md:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">Märkused</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->notes }}</dd>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Related Deals -->
                    @if($customer->deals->count() > 0)
                        <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Seotud tehingud</h3>
                                <div class="space-y-3">
                                    @foreach($customer->deals as $deal)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $deal->title }}</p>
                                                <p class="text-sm text-gray-500">€{{ number_format($deal->value, 2) }} - 
                                                    @if($deal->stage === 'lead') Potentsiaalne
                                                    @elseif($deal->stage === 'qualified') Kvalifitseeritud
                                                    @elseif($deal->stage === 'proposal') Pakkumine
                                                    @elseif($deal->stage === 'negotiation') Läbirääkimised
                                                    @elseif($deal->stage === 'closed_won') Võidetud
                                                    @elseif($deal->stage === 'closed_lost') Kaotatud
                                                    @else {{ ucfirst(str_replace('_', ' ', $deal->stage)) }} @endif
                                                </p>
                                            </div>
                                            <a href="{{ route('deals.show', $deal) }}" class="text-blue-600 hover:text-blue-800">
                                                Vaata
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Related Tasks -->
                    @if($customer->tasks->count() > 0)
                        <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Seotud ülesanded</h3>
                                <div class="space-y-3">
                                    @foreach($customer->tasks as $task)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $task->title }}</p>
                                                <p class="text-sm text-gray-500">{{ ucfirst($task->type) }} - {{ $task->priority === 'high' ? 'Kõrge' : ($task->priority === 'medium' ? 'Keskmine' : 'Madal') }} prioriteet</p>
                                                @if($task->due_date)
                                                    <p class="text-xs text-gray-400">Tähtaeg: {{ $task->due_date->format('d.m.Y') }}</p>
                                                @endif
                                            </div>
                                            <a href="{{ route('tasks.show', $task) }}" class="text-blue-600 hover:text-blue-800">
                                                Vaata
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Quick Actions -->
                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Kiirtegevused</h3>
                            <div class="space-y-3">
                                <a href="{{ route('deals.create', ['customer_id' => $customer->id]) }}" class="block w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">
                                    Loo tehing
                                </a>
                                <a href="{{ route('tasks.create', ['customer_id' => $customer->id]) }}" class="block w-full bg-green-50 hover:bg-green-100 text-green-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">
                                    Loo ülesanne
                                </a>
                                <a href="{{ route('contacts.create', ['customer_id' => $customer->id]) }}" class="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium py-2 px-4 rounded-lg text-center transition-colors">
                                    Lisa kontakt
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Stats -->
                    <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Statistika</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Tehinguid kokku</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $customer->deals->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Tehingute väärtus</span>
                                    <span class="text-sm font-medium text-gray-900">€{{ number_format($customer->deals->sum('value'), 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Võidetud tehingud</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $customer->deals->where('stage', 'closed_won')->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Aktiivsed ülesanded</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $customer->tasks->whereNotIn('status', ['completed', 'cancelled'])->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Kontaktid</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $customer->contacts->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
