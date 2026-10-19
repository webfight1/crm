@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
@endif
@if($messengerProblem = app(\App\Chats\Services\MessengerBridge::class)->problem())
    <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded">
        {{ $messengerProblem }}
        @unless(request()->routeIs('chats.connect'))<a href="{{ route('chats.connect') }}" class="underline font-medium">Ühenda uuesti</a>@endunless
    </div>
@endif
