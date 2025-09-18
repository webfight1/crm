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
                            <dd class="mt-1 text-sm text-gray-900">{{ number_format($deal->value, 2) }} €</dd>
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
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $deal->description }}</dd>
                        </div>
                        @endif

                        @if($deal->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Märkused</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $deal->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
