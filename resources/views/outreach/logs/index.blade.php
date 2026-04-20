<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Saatmislogi — {{ $campaign->name }}</h2>
            <a href="{{ route('outreach.campaigns.show', $campaign) }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Kampaania</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Samm</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teema</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Postkast</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Olek</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aeg</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($logs as $log)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $log->to_email }}</p>
                                @if($log->lead)
                                    <p class="text-xs text-gray-500">{{ $log->lead->first_name }} {{ $log->lead->last_name }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">#{{ $log->step_order }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">{{ $log->subject }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $log->from_email }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusClasses = [
                                        'sent'    => 'bg-green-100 text-green-700',
                                        'failed'  => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'skipped' => 'bg-gray-100 text-gray-600',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses[$log->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $log->status }}
                                </span>
                                @if($log->error_message)
                                    <p class="text-xs text-red-500 mt-1 max-w-xs truncate" title="{{ $log->error_message }}">
                                        {{ Str::limit($log->error_message, 60) }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ ($log->sent_at ?? $log->created_at)?->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($log->body)
                                    <button
                                        onclick="openEmailModal(this)"
                                        data-subject="{{ e($log->subject) }}"
                                        data-to="{{ e($log->to_email) }}"
                                        data-body="{{ e($log->body) }}"
                                        class="text-indigo-600 hover:text-indigo-900 text-xs font-medium"
                                    >Vaata kirja</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">Logisid pole.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($logs->hasPages())
                    <div class="px-6 py-4 border-t">{{ $logs->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Email preview modal --}}
    <div id="emailModal" class="fixed inset-0 z-50 hidden" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" onclick="closeEmailModal()"></div>
        <div class="absolute inset-4 sm:inset-10 bg-white rounded-lg shadow-xl flex flex-col overflow-hidden">
            <div class="flex items-start justify-between px-6 py-4 border-b bg-gray-50">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Saaja: <span id="modalTo" class="font-medium text-gray-700"></span></p>
                    <p class="text-sm font-semibold text-gray-900" id="modalSubject"></p>
                </div>
                <button onclick="closeEmailModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none ml-4">&times;</button>
            </div>
            <div class="flex-1 overflow-auto">
                <iframe id="modalFrame" class="w-full h-full border-0" sandbox="allow-same-origin"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openEmailModal(btn) {
            document.getElementById('modalTo').textContent      = btn.dataset.to;
            document.getElementById('modalSubject').textContent = btn.dataset.subject;

            const frame = document.getElementById('modalFrame');
            const doc   = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(btn.dataset.body);
            doc.close();

            document.getElementById('emailModal').classList.remove('hidden');
        }

        function closeEmailModal() {
            document.getElementById('emailModal').classList.add('hidden');

            const frame = document.getElementById('modalFrame');
            const doc   = frame.contentDocument || frame.contentWindow.document;
            doc.open(); doc.write(''); doc.close();
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEmailModal();
        });
    </script>
</x-app-layout>
