<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Uus Email Kampaania') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-6">
                        <strong>Märkus:</strong> Maksimaalne kirjade arv on 5000. Kirjad saadetakse 7-sekundilise vahega.
                    </div>

                    <form id="emailForm" method="POST" action="{{ route('email-campaigns.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- CSV File -->
                            <div class="md:col-span-2">
                                <x-input-label for="csv_file" :value="__('CSV fail e-maili aadressidega')" />
                                <input id="csv_file" name="csv_file" type="file" accept=".csv" required
                                    class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
                            </div>

                            <!-- Email Column -->
                            <div>
                                <x-input-label for="email_column" :value="__('E-maili aadressi veeru nimi CSV failis')" />
                                <x-text-input id="email_column" class="block mt-1 w-full" type="text" name="email_column" :value="old('email_column')" required />
                                <x-input-error :messages="$errors->get('email_column')" class="mt-2" />
                            </div>

                            <!-- Name Column -->
                            <div>
                                <x-input-label for="name_column" :value="__('Ettevõtte nime veeru nimi (valikuline)')" />
                                <x-text-input id="name_column" class="block mt-1 w-full" type="text" name="name_column" :value="old('name_column', 'name')" placeholder="name" />
                                <p class="text-sm text-gray-500 mt-1">Kasuta {company_name} muutujat e-maili sisus</p>
                                <x-input-error :messages="$errors->get('name_column')" class="mt-2" />
                            </div>

                            <!-- Subject -->
                            <div>
                                <x-input-label for="subject" :value="__('E-maili teema')" />
                                <x-text-input id="subject" class="block mt-1 w-full" type="text" name="subject" :value="old('subject')" required />
                                <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                            </div>

                            <!-- Subject RU -->
                            <div>
                                <x-input-label for="subject_ru" :value="__('Vene keele teema (.ru e-mailidele)')" />
                                <x-text-input id="subject_ru" class="block mt-1 w-full" type="text" name="subject_ru" :value="old('subject_ru')" placeholder="Sisesta vene keele teema .ru lõpuga e-maili aadressidele..." />
                                <x-input-error :messages="$errors->get('subject_ru')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Message -->
                        <div class="mt-6">
                            <x-input-label for="message" :value="__('E-maili sisu (HTML lubatud)')" />
                            <textarea id="message" name="message" rows="10" required
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('message') }}</textarea>
                            <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            
                            <!-- Preview Container -->
                            <div class="mt-4 border border-gray-200 rounded-lg bg-white" id="previewContainer">
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700">HTML Eelvaade</span>
                                    <button type="button" class="text-sm text-blue-600 hover:text-blue-800" onclick="togglePreview('previewContainer')">Peida</button>
                                </div>
                                <div class="p-4 min-h-[100px] max-h-[300px] overflow-y-auto" id="previewContent">
                                    <em class="text-gray-500">Sisesta HTML sisu üleval, et näha eelvaadet...</em>
                                </div>
                            </div>
                        </div>

                        <!-- Message RU -->
                        <div class="mt-6">
                            <x-input-label for="message_ru" :value="__('Vene keele tõlge (.ru e-mailidele)')" />
                            <textarea id="message_ru" name="message_ru" rows="10"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" 
                                placeholder="Sisesta vene keele tõlge .ru lõpuga e-maili aadressidele saatmiseks...">{{ old('message_ru') }}</textarea>
                            <x-input-error :messages="$errors->get('message_ru')" class="mt-2" />
                            
                            <!-- Preview Container RU -->
                            <div class="mt-4 border border-gray-200 rounded-lg bg-white" id="previewContainerRu">
                                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700">HTML Eelvaade (Vene keel)</span>
                                    <button type="button" class="text-sm text-blue-600 hover:text-blue-800" onclick="togglePreview('previewContainerRu')">Peida</button>
                                </div>
                                <div class="p-4 min-h-[100px] max-h-[300px] overflow-y-auto" id="previewContentRu">
                                    <em class="text-gray-500">Sisesta vene keele HTML sisu üleval, et näha eelvaadet...</em>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('email-campaigns.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                            <x-primary-button>
                                {{ __('Alusta Saatmist') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Progress Section (hidden initially) -->
                    <div id="progress" class="hidden mt-8 p-6 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Saatmise Progress</h3>
                        <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
                            <div class="bg-green-600 h-4 rounded-full transition-all duration-500 ease-out" style="width: 0%" id="progressBar"></div>
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <span id="progressText" class="text-sm text-gray-600">0/0 kirja saadetud</span>
                            <div class="text-sm text-gray-500">
                                Järgmine kiri: <span id="countdown">7</span> sekundi pärast
                            </div>
                        </div>
                        <div id="log" class="max-h-60 overflow-y-auto bg-white p-4 rounded border text-sm"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // HTML preview functionality
        function togglePreview(containerId) {
            const container = document.getElementById(containerId);
            const button = container.querySelector('button');
            const content = container.querySelector('[id$="Content"]');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                button.textContent = 'Peida';
            } else {
                content.style.display = 'none';
                button.textContent = 'Näita';
            }
        }
        
        function updatePreview(textareaId, previewId) {
            const textarea = document.getElementById(textareaId);
            const preview = document.getElementById(previewId);
            
            if (textarea.value.trim() === '') {
                preview.innerHTML = '<em class="text-gray-500">Sisesta HTML sisu üleval, et näha eelvaadet...</em>';
            } else {
                preview.innerHTML = textarea.value;
            }
        }
        
        // Add event listeners for real-time preview updates
        document.addEventListener('DOMContentLoaded', function() {
            const messageTextarea = document.getElementById('message');
            const messageRuTextarea = document.getElementById('message_ru');
            
            // Update preview on input
            messageTextarea.addEventListener('input', function() {
                updatePreview('message', 'previewContent');
            });
            
            messageRuTextarea.addEventListener('input', function() {
                updatePreview('message_ru', 'previewContentRu');
            });
            
            // Update preview on paste
            messageTextarea.addEventListener('paste', function() {
                setTimeout(() => updatePreview('message', 'previewContent'), 10);
            });
            
            messageRuTextarea.addEventListener('paste', function() {
                setTimeout(() => updatePreview('message_ru', 'previewContentRu'), 10);
            });
        });

        // Form submission with progress tracking
        document.getElementById('emailForm').onsubmit = function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            document.getElementById('progress').classList.remove('hidden');
            
            // Submit form
            fetch('{{ route("email-campaigns.store") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => {
                if (response.ok) {
                    // Start progress tracking
                    checkProgress();
                } else {
                    console.error('Form submission failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        };

        let logCount = 0;
        let countdownTimer = null;
        
        function checkProgress() {
            fetch('{{ route("email-campaigns.progress") }}')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'running' || data.status === 'completed') {
                    updateProgress(data.current, data.total, data.message);
                    
                    // Update countdown timer
                    if (data.nextSendIn > 0) {
                        startCountdown(data.nextSendIn);
                    }
                    
                    if (data.status === 'running') {
                        setTimeout(checkProgress, 1000);
                    } else {
                        // Redirect to index after completion
                        setTimeout(() => {
                            window.location.href = '{{ route("email-campaigns.index") }}';
                        }, 3000);
                    }
                }
            });
        }

        function updateProgress(current, total, message) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const log = document.getElementById('log');
            
            // Update progress bar
            const percentage = (current / total) * 100;
            progressBar.style.width = percentage + '%';
            
            // Update progress text
            progressText.textContent = `${current}/${total} kirja saadetud (${percentage.toFixed(1)}%)`;
            
            // Add log entry if there's a new message
            if (message) {
                const logEntry = document.createElement('div');
                logEntry.className = 'text-green-600';
                logEntry.textContent = `[${++logCount}] ${message}`;
                log.insertBefore(logEntry, log.firstChild);
            }
        }

        function startCountdown(seconds) {
            const countdownElement = document.getElementById('countdown');
            
            // Clear previous timer if exists
            if (countdownTimer) {
                clearInterval(countdownTimer);
            }
            
            countdownElement.textContent = seconds;
            
            countdownTimer = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownTimer);
                }
            }, 1000);
        }
    </script>
</x-app-layout>
