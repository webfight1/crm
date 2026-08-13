<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Meriti võlgnike meeldetuletused') }}
            </h2>
            <div class="space-x-2">
                <a href="{{ route('meriti.logs') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Ajalugu') }}</a>
                <a href="{{ route('meriti.settings') }}" class="inline-flex items-center px-3 py-1.5 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">{{ __('Seaded') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            @if($settings->test_recipient)
                <div class="bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 rounded flex items-center gap-2">
                    <span class="text-lg">🧪</span>
                    <span>{{ __('Testrežiim on sees — kõik meeldetuletused lähevad aadressile') }} <strong>{{ $settings->test_recipient }}</strong>, {{ __('mitte päris klientidele. Olekut ei muudeta.') }}</span>
                </div>
            @endif

            {{-- Olek + ühendus --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div>
                        <span class="text-sm text-gray-500">{{ __('Automaatika') }}</span><br>
                        @if($settings->enabled)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">{{ __('Sees') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-gray-100 text-gray-700">{{ __('Väljas') }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">{{ __('Meriti ühendus') }}</span><br>
                        @if($connection['ok'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">{{ __('Töötab') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-800" title="{{ $connection['message'] }}">{{ __('Viga') }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">{{ __('Astmed (päeva üle tähtaja)') }}</span><br>
                        <span class="text-sm text-gray-800">
                            @foreach([1,2,3] as $l)
                                @php($step = $settings->step($l))
                                @if($step['enabled'])
                                    <span class="mr-2">{{ $l }}. {{ $step['days'] }}p</span>
                                @endif
                            @endforeach
                        </span>
                    </div>
                    <div class="ml-auto">
                        <form method="POST" action="{{ route('meriti.send-now') }}" onsubmit="return confirm('{{ __('Saada meeldetuletused kohe kõigile, kellel aste käes?') }}');">
                            @csrf
                            <x-primary-button type="submit">{{ __('Saada kohe') }}</x-primary-button>
                        </form>
                    </div>
                </div>

                @unless($connection['ok'])
                    <p class="mt-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                        {{ $connection['message'] }}
                    </p>
                @endunless
            </div>

            {{-- Praeguste võlgnike eelvaade --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Praegused üle tähtaja võlgnikud') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Eelvaade Meritist. "Järgmine aste" näitab, milline meeldetuletus järgmisena välja läheks.') }}</p>
                </div>

                @if($error)
                    <div class="px-6 py-4 text-red-700">{{ __('Andmete laadimine ebaõnnestus: ') }}{{ $error }}</div>
                @elseif($plan->isEmpty())
                    <div class="px-6 py-8 text-center text-gray-500">{{ __('Üle tähtaja võlgnikke ei leitud.') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Klient') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Kontakt') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('E-post') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Arveid') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Tasumata') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Päevi üle') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500">{{ __('Saadetud') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-500">{{ __('Järgmine aste') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($plan as $row)
                                    @php($d = $row['debtor'])
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900">{{ $d->name ?: '—' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $d->contact ?: '—' }}</td>
                                        <td class="px-4 py-3">
                                            @if($d->hasEmail())
                                                <span class="text-gray-700">{{ $d->email }}</span>
                                            @else
                                                <span class="text-red-600">{{ __('puudub') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ count($d->invoices) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900 whitespace-nowrap">{{ $d->formattedTotal() }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $d->maxOverdueDays }}</td>
                                        <td class="px-4 py-3 text-center text-gray-500">{{ $row['state']->highest_level_sent ?: '—' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($row['level'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $row['level'] }}. {{ __('kiri') }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
