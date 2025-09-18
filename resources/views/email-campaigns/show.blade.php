<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Email Kampaania Detailid') }}
            </h2>
            <a href="{{ route('email-campaigns.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Tagasi Nimekirja
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Saaja</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->recipient_email }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->company_name ?: 'N/A' }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Teema</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->subject }}</dd>
                        </div>

                        @if($emailCampaign->subject_ru)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Teema (Vene keel)</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->subject_ru }}</dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Staatus</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($emailCampaign->status === 'sent') bg-green-100 text-green-800
                                    @elseif($emailCampaign->status === 'failed') bg-red-100 text-red-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    @if($emailCampaign->status === 'sent') Saadetud
                                    @elseif($emailCampaign->status === 'failed') Ebaõnnestus
                                    @else Ootel @endif
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Saadetud</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $emailCampaign->sent_at ? $emailCampaign->sent_at->format('d.m.Y H:i:s') : 'Pole veel saadetud' }}
                            </dd>
                        </div>

                        @if($emailCampaign->csv_filename)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">CSV Fail</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->csv_filename }}</dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $emailCampaign->created_at->format('d.m.Y H:i:s') }}</dd>
                        </div>
                    </div>

                    @if($emailCampaign->error_message)
                    <div class="mb-6">
                        <dt class="text-sm font-medium text-gray-500">Vea Sõnum</dt>
                        <dd class="mt-1 text-sm text-red-600 bg-red-50 p-3 rounded">{{ $emailCampaign->error_message }}</dd>
                    </div>
                    @endif

                    <!-- Email Content -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">E-maili Sisu</h3>
                        <div class="border border-gray-200 rounded-lg">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <span class="text-sm font-medium text-gray-700">HTML Sisu</span>
                            </div>
                            <div class="p-4 max-h-96 overflow-y-auto">
                                {!! $emailCampaign->message !!}
                            </div>
                        </div>
                    </div>

                    @if($emailCampaign->message_ru)
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">E-maili Sisu (Vene keel)</h3>
                        <div class="border border-gray-200 rounded-lg">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <span class="text-sm font-medium text-gray-700">HTML Sisu (Vene keel)</span>
                            </div>
                            <div class="p-4 max-h-96 overflow-y-auto">
                                {!! $emailCampaign->message_ru !!}
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Raw HTML View -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">HTML Kood</h3>
                        <div class="border border-gray-200 rounded-lg">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <span class="text-sm font-medium text-gray-700">HTML Lähtekood</span>
                            </div>
                            <div class="p-4 bg-gray-900 text-green-400 max-h-96 overflow-y-auto">
                                <pre class="text-sm"><code>{{ $emailCampaign->message }}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
