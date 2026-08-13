<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Klientide e-postid') }}
            </h2>
            <a href="{{ route('meriti.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Tagasi') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 text-sm text-gray-600">
                {{ __('Kui kliendil pole Meritis e-posti, saad selle siia käsitsi lisada — meeldetuletus saadetakse siis siin lisatud aadressile. Kellel Meritis e-post olemas, see jääb kehtima (Meriti oma võidab).') }}
            </div>

            @if($error)
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ __('Andmete laadimine ebaõnnestus: ') }}{{ $error }}</div>
            @elseif(!$connection['ok'])
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ $connection['message'] }}</div>
            @elseif($debtors->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg px-6 py-8 text-center text-gray-500">{{ __('Võlgnikke ei leitud.') }}</div>
            @else
                @php $missing = $debtors->filter(fn($d) => !$d->hasEmail())->count(); @endphp
                <form method="POST" action="{{ route('meriti.emails.store') }}">
                    @csrf
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Võlgnikud') }} ({{ $debtors->count() }})</h3>
                                @if($missing > 0)
                                    <p class="text-sm text-amber-700">{{ $missing }} {{ __('kliendil puudub e-post') }}</p>
                                @else
                                    <p class="text-sm text-green-700">{{ __('Kõigil on e-post olemas') }}</p>
                                @endif
                            </div>
                            <x-primary-button type="submit">{{ __('Salvesta e-postid') }}</x-primary-button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('Klient') }}</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Tasumata') }}</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">{{ __('Päevi üle') }}</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 w-96">{{ __('E-post') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($debtors as $d)
                                        <tr class="{{ !$d->hasEmail() ? 'bg-amber-50' : '' }}">
                                            <td class="px-4 py-3 text-gray-900">{{ $d->name ?: '—' }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 whitespace-nowrap">{{ $d->formattedTotal() }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700">{{ $d->maxOverdueDays }}</td>
                                            <td class="px-4 py-3">
                                                @if($d->emailSource === 'merit')
                                                    <span class="text-gray-700">{{ $d->email }}</span>
                                                    <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Meritist') }}</span>
                                                @else
                                                    <input type="hidden" name="names[{{ $d->customerId }}]" value="{{ $d->name }}">
                                                    <input type="email" name="emails[{{ $d->customerId }}]"
                                                           value="{{ old('emails.'.$d->customerId, $overrides[$d->customerId] ?? '') }}"
                                                           placeholder="{{ __('lisa e-post') }}"
                                                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                                    @if($d->emailSource === 'manual')
                                                        <span class="text-xs text-indigo-600">{{ __('käsitsi lisatud') }}</span>
                                                    @endif
                                                    <x-input-error :messages="$errors->get('emails.'.$d->customerId)" class="mt-1" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                            <x-primary-button type="submit">{{ __('Salvesta e-postid') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
