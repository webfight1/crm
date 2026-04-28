<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $contact->name }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('contacts.edit', $contact) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Muuda
                </a>
                <a href="{{ route('contacts.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Tagasi
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($outreachActivity['has_activity'])
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">📬 Outreach vestlus</h3>
                            <a href="{{ $outreachActivity['inbox_url'] }}" class="text-sm text-purple-600 hover:text-purple-800 font-medium">Ava vestlus →</a>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                            <div>
                                <dt class="text-xs text-gray-500">Vastuseid</dt>
                                <dd class="text-2xl font-semibold text-purple-700">{{ $outreachActivity['reply_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Saadetud</dt>
                                <dd class="text-2xl font-semibold text-gray-700">{{ $outreachActivity['sent_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Kampaaniaid</dt>
                                <dd class="text-2xl font-semibold text-gray-700">{{ $outreachActivity['campaigns']->count() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Viimane vastus</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">
                                    @if($outreachActivity['last_received_at'])
                                        {{ $outreachActivity['last_received_at']->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        </div>

                        @if($outreachActivity['latest_subject'] || $outreachActivity['latest_snippet'])
                            <div class="p-3 bg-purple-50 border border-purple-100 rounded">
                                @if($outreachActivity['latest_subject'])
                                    <p class="text-sm font-medium text-gray-900">{{ $outreachActivity['latest_subject'] }}</p>
                                @endif
                                @if($outreachActivity['latest_snippet'])
                                    <p class="text-sm text-gray-600 mt-1">{{ $outreachActivity['latest_snippet'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Nimi</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $contact->name }}</dd>
                        </div>

                        @if($contact->email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">E-mail</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $contact->email }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($contact->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Telefon</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $contact->phone }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $contact->phone }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($contact->position)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ametikoht</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $contact->position }}</dd>
                        </div>
                        @endif

                        @if($contact->company)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('companies.show', $contact->company) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $contact->company->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $contact->created_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uuendatud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $contact->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        @if($contact->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Märkused</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $contact->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
