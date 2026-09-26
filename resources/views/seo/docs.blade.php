<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SEO protsessi kirjeldus</h2>
            <a href="{{ route('seo.playbook') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Playbook</a>
        </div>
    </x-slot>

    {{-- No typography plugin in this app — minimal article styles, scoped. --}}
    <style>
        .seo-doc { color: #1f2937; font-size: .9375rem; line-height: 1.65; }
        .seo-doc h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 1rem; }
        .seo-doc h2 { font-size: 1.2rem; font-weight: 600; margin: 2rem 0 .75rem; padding-bottom: .35rem; border-bottom: 1px solid #e5e7eb; }
        .seo-doc h3 { font-size: 1.05rem; font-weight: 600; margin: 1.5rem 0 .5rem; }
        .seo-doc p, .seo-doc ul, .seo-doc ol, .seo-doc table, .seo-doc pre { margin: 0 0 1rem; }
        .seo-doc ul { list-style: disc; padding-left: 1.5rem; }
        .seo-doc ol { list-style: decimal; padding-left: 1.5rem; }
        .seo-doc li { margin: .2rem 0; }
        .seo-doc li:has(> input[type=checkbox]) { list-style: none; margin-left: -1.25rem; }
        .seo-doc input[type=checkbox] { margin-right: .4rem; border-radius: .2rem; }
        .seo-doc code { background: #f3f4f6; padding: .1rem .3rem; border-radius: .25rem; font-size: .85em; }
        .seo-doc pre { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; overflow-x: auto; font-size: .8rem; line-height: 1.45; }
        .seo-doc pre code { background: none; padding: 0; font-size: inherit; }
        .seo-doc table { width: 100%; border-collapse: collapse; font-size: .875rem; display: block; overflow-x: auto; }
        .seo-doc th, .seo-doc td { border: 1px solid #e5e7eb; padding: .4rem .6rem; text-align: left; vertical-align: top; }
        .seo-doc th { background: #f9fafb; font-weight: 600; }
        .seo-doc a { color: #4f46e5; }
        .seo-doc strong { font-weight: 600; }
    </style>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <article class="seo-doc bg-white shadow-sm rounded-lg p-6 md:p-8">
                {!! $html !!}
            </article>
            <p class="mt-3 text-xs text-gray-500">
                Allikas: <code>docs/seo-automation.md</code> koodihoidlas{{ $updatedAt ? ' · uuendatud ' . $updatedAt->format('d.m.Y H:i') : '' }}.
                Muudatused tulevad siia koos järgmise koodiuuendusega.
            </p>
        </div>
    </div>
</x-app-layout>
