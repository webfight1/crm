{{-- Agreed elsewhere (phone, meeting): mark the quotation accepted without sending it. --}}
@if(in_array($quotation->status, ['draft', 'sent'], true))
    @php $canStart = $quotation->deal && in_array($quotation->deal->stage, ['lead', 'qualified', 'proposal', 'negotiation'], true); @endphp
    <div class="mb-6 p-3 rounded-md border border-green-200 bg-green-50 text-sm flex flex-wrap items-center gap-3">
        <span class="text-gray-700">Kokkulepe tehtud muul teel? Märgi pakkumine vastu võetuks ilma seda saatmata:</span>
        <form method="POST" action="{{ route('quotations.accept', $quotation) }}" onsubmit="return confirm('Märkida pakkumine {{ $quotation->number }} vastu võetuks?')">
            @csrf
            <button class="text-xs border border-green-400 text-green-800 bg-white rounded px-3 py-1 hover:bg-green-100">✅ Klient kinnitas</button>
        </form>
        @if($canStart)
            <form method="POST" action="{{ route('quotations.accept', $quotation) }}" onsubmit="return confirm('Märkida pakkumine vastu võetuks ja tehing „töös“? (SEO-kliendil tekib ka ligipääsukirja mustand)')">
                @csrf
                <input type="hidden" name="start" value="1">
                <button class="text-xs border border-green-600 text-white bg-green-600 rounded px-3 py-1 hover:bg-green-700">✅ Kinnitas + pane töösse</button>
            </form>
        @endif
    </div>
@endif
