<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $company->name }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('companies.edit', $company) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Muuda
                </a>
                <a href="{{ route('companies.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
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
                            <dt class="text-sm font-medium text-gray-500">Ettevõtte nimi</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->name }}</dd>
                        </div>

                        @if($company->email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">E-mail</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $company->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $company->email }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($company->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Telefon</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $company->phone }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $company->phone }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($company->website)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Veebileht</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ $company->website }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                    {{ $company->website }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($company->address)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Aadress</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->address }}</dd>
                        </div>
                        @endif

                        @if($company->industry)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Valdkond</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->industry }}</dd>
                        </div>
                        @endif

                        @if($company->size)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Suurus</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->size }}</dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->created_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uuendatud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        @if($company->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Märkused</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $company->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
