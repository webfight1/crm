<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Outreach — Inbox</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('outreach.dashboard') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Töölaud</a>
                <form method="POST" action="{{ route('outreach.trigger.reply-check') }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                        ↻ Kontrolli vastuseid
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-1 space-y-4">
                    @include('outreach.inbox._watched_panel')
                    @include('outreach.inbox._list')
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white shadow-sm rounded-lg p-12 text-center text-gray-500" style="min-height: 400px;">
                        <div class="flex flex-col items-center justify-center h-full">
                            <span class="text-5xl mb-3">📬</span>
                            <p class="text-sm">Vali vasakult vestlus, et seda lugeda ja vastata.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
