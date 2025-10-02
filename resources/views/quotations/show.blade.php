<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Pakkumine') }} #{{ $quotation->number }}
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('quotations.edit', $quotation) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Muuda') }}
                </a>
                <a href="{{ route('quotations.pdf', $quotation) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('PDF') }}
                </a>
                @if($quotation->status === 'draft')
                    <x-modal name="confirm-send" :show="$errors->sendQuotation->isNotEmpty()" focusable>
                        <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="p-6">
                            @csrf

                            <h2 class="text-lg font-medium text-gray-900">
                                {{ __('Kas soovid saata pakkumise e-postiga?') }}
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Pakkumine saadetakse aadressile:') }} <strong>{{ $quotation->deal->customer->email }}</strong>
                            </p>

                            <div class="mt-6 flex justify-end">
                                <x-secondary-button x-on:click="$dispatch('close')">
                                    {{ __('Tühista') }}
                                </x-secondary-button>

                                <x-primary-button class="ml-3">
                                    {{ __('Saada') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </x-modal>

                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-send')">
                        {{ __('Saada e-postiga') }}
                    </x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Pakkumise info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h3 class="text-lg font-medium mb-4">{{ __('Pakkumise info') }}</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Deal') }}</dt>
                                    <dd class="mt-1">
                                        <a href="{{ route('deals.show', $quotation->deal) }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $quotation->deal->title }}
                                        </a>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Staatus') }}</dt>
                                    <dd class="mt-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($quotation->status === 'draft') bg-gray-100 text-gray-800
                                            @elseif($quotation->status === 'sent') bg-blue-100 text-blue-800
                                            @elseif($quotation->status === 'accepted') bg-green-100 text-green-800
                                            @else bg-red-100 text-red-800
                                            @endif">
                                            {{ ucfirst($quotation->status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Kehtiv kuni') }}</dt>
                                    <dd class="mt-1">{{ $quotation->valid_until ? $quotation->valid_until->format('d.m.Y') : '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-medium mb-4">{{ __('Kliendi info') }}</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Klient') }}</dt>
                                    <dd class="mt-1">
                                        <a href="{{ route('customers.show', $quotation->deal->customer) }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $quotation->deal->customer->full_name }}
                                        </a>
                                    </dd>
                                </div>
                                @if($quotation->deal->company)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Ettevõte') }}</dt>
                                        <dd class="mt-1">
                                            <a href="{{ route('companies.show', $quotation->deal->company) }}" class="text-blue-600 hover:text-blue-900">
                                                {{ $quotation->deal->company->name }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Pakkumise read -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-4">{{ __('Pakkumise read') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Kirjeldus') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Kogus') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Ühik') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Ühiku hind') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Summa') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($quotation->items as $item)
                                        <tr>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                {{ $item->description }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ number_format($item->quantity, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $item->unit }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                €{{ number_format($item->unit_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                €{{ number_format($item->subtotal, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-500">
                                            {{ __('Summa käibemaksuta:') }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">
                                            €{{ number_format($quotation->subtotal, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-500">
                                            {{ __('Käibemaks') }} ({{ $quotation->vat_rate }}%):
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">
                                            €{{ number_format($quotation->vat_amount, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                            {{ __('Kokku:') }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-bold text-gray-900">
                                            €{{ number_format($quotation->total, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Tingimused ja märkused -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($quotation->terms)
                            <div>
                                <h3 class="text-lg font-medium mb-2">{{ __('Maksetingimused') }}</h3>
                                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $quotation->terms }}</p>
                            </div>
                        @endif

                        @if($quotation->notes)
                            <div>
                                <h3 class="text-lg font-medium mb-2">{{ __('Märkused') }}</h3>
                                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $quotation->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
