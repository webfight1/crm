<x-mail::message>
# {{ __('Tere') }}, {{ $quotation->deal->customer->full_name }}!

{{ __('Saadame Teile pakkumise') }} #{{ $quotation->number }}.

**{{ $quotation->title }}**

{{ $quotation->description }}

<x-mail::button :url="route('quotations.show', $quotation)">
{{ __('Vaata pakkumist') }}
</x-mail::button>

{{ __('Pakkumine on lisatud manusesse PDF-failina.') }}

{{ __('Lugupidamisega') }},<br>
{{ $quotation->user->name }}<br>
{{ config('app.name') }}
</x-mail::message>
