<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Email Logid & Cooldown Staatus') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Cooldown Statistics -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">🛡️ Cooldown Kaitse</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ env('EMAIL_COOLDOWN_DAYS', 14) }}</div>
                            <div class="text-sm text-blue-800">päeva cooldown periood</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ count($cooldownEmails) }}</div>
                            <div class="text-sm text-yellow-800">e-maili cooldown-is</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $logs->total() }}</div>
                            <div class="text-sm text-green-800">kokku saadetud</div>
                        </div>
                    </div>

                    <!-- Cooldown Email Checker -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-2">Kontrolli e-maili cooldown staatust</h4>
                        <div class="flex gap-2">
                            <input type="email" id="emailCheck" placeholder="sisesta@email.com" 
                                class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <button onclick="checkCooldown()" 
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Kontrolli
                            </button>
                        </div>
                        <div id="cooldownResult" class="mt-2 text-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Email Logs Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Saadetud E-mailid</h3>
                    
                    @if($logs->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-mail</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teema</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staatus</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saadetud</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cooldown</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($logs as $log)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $log->email }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ Str::limit($log->subject, 50) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    @if($log->status === 'sent') bg-green-100 text-green-800
                                                    @elseif($log->status === 'failed') bg-red-100 text-red-800
                                                    @else bg-yellow-100 text-yellow-800 @endif">
                                                    @if($log->status === 'sent') Saadetud
                                                    @elseif($log->status === 'failed') Ebaõnnestunud
                                                    @else {{ $log->status }} @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $log->sent_at->format('d.m.Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $cooldownEnd = $log->sent_at->addDays(env('EMAIL_COOLDOWN_DAYS', 14));
                                                    $isInCooldown = $cooldownEnd->isFuture();
                                                @endphp
                                                @if($isInCooldown)
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Kuni {{ $cooldownEnd->format('d.m.Y') }}
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Valmis
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $logs->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 7.89a1 1 0 001.42 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Pole veel e-maile saadetud</h3>
                            <p class="mt-1 text-sm text-gray-500">Alusta oma esimese email kampaaniaga.</p>
                            <div class="mt-6">
                                <a href="{{ route('email-campaigns.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Loo Email Kampaania
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        async function checkCooldown() {
            const email = document.getElementById('emailCheck').value;
            const resultDiv = document.getElementById('cooldownResult');
            
            if (!email) {
                resultDiv.innerHTML = '<span class="text-red-600">Palun sisesta e-mail aadress</span>';
                return;
            }
            
            try {
                const response = await fetch(`{{ route('email-logs.cooldown-status') }}?email=${encodeURIComponent(email)}`);
                const data = await response.json();
                
                if (data.in_cooldown) {
                    resultDiv.innerHTML = `<span class="text-yellow-600">⏸️ ${data.message}</span>`;
                } else {
                    resultDiv.innerHTML = `<span class="text-green-600">✅ ${data.message}</span>`;
                }
            } catch (error) {
                resultDiv.innerHTML = '<span class="text-red-600">Viga cooldown kontrollimisel</span>';
            }
        }
        
        // Allow Enter key to trigger check
        document.getElementById('emailCheck').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                checkCooldown();
            }
        });
    </script>
</x-app-layout>
