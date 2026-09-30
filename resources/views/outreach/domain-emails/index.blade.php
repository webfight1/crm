<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Emailid domeeni järgi</h2>
            <a href="{{ route('outreach.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Kampaaniad →</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(isset($errors) && $errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('outreach.domain-emails.enrich') }}" enctype="multipart/form-data"
                  class="bg-white shadow-sm rounded-lg p-5 space-y-4">
                @csrf

                <div>
                    <label for="domain-csv" class="block text-sm font-medium text-gray-700 mb-1">CSV fail</label>
                    <input type="file" name="file" id="domain-csv" accept=".csv,text/csv" required class="block w-full text-sm">
                    <p class="text-xs text-gray-400 mt-2">
                        Domeen võetakse veerust „Domeen”, „Koduleht”, „Website”, „URL” vms (või esimesest veerust, kus on domeenid).
                        Sobib nii <code>foo.ee</code> kui <code>https://www.foo.ee/leht</code>. Eraldaja (<code>;</code> või <code>,</code>) tuvastatakse ise.
                    </p>
                </div>

                <div class="text-sm text-gray-600 bg-gray-50 rounded p-3">
                    Tagasi saad sama CSV, lõppu lisatakse veerud:
                    <strong>Email</strong> (parim — eelistatakse sama domeeni aadressi), <strong>Kõik emailid</strong>,
                    <strong>Ettevõte</strong>, <strong>Registrikood</strong>, <strong>Telefon</strong> ja
                    <strong>Leitud</strong> (<em>www</em> = ettevõtte kodulehe järgi, <em>e-posti domeen</em> = leitud @domeen aadressi järgi).
                    Otsitakse ettevõtete andmebaasist.
                </div>

                <x-primary-button>Otsi emailid ja laadi CSV alla</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
