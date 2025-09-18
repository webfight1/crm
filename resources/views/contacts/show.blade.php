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
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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
