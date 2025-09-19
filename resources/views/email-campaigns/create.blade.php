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
                    <form id="emailForm" method="POST" action="{{ route('email-campaigns.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Campaign Name -->
                            <div class="md:col-span-2">
                                <x-input-label for="campaign_name" :value="__('Kampaania nimi')" />
                                <x-text-input id="campaign_name" name="campaign_name" type="text" 
                                    class="mt-1 block w-full" :value="old('campaign_name')" required />
                                <x-input-error :messages="$errors->get('campaign_name')" class="mt-2" />
                            </div>

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
                                <x-text-input id="email_column" name="email_column" type="text" 
                                    class="mt-1 block w-full" :value="old('email_column', 'email')" required />
                                <x-input-error :messages="$errors->get('email_column')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">Näiteks: email, e-mail, Email</p>
                            </div>

                            <!-- Name Column -->
                            <div>
                                <x-input-label for="name_column" :value="__('Nime veeru nimi CSV failis (valikuline)')" />
                                <x-text-input id="name_column" name="name_column" type="text" 
                                    class="mt-1 block w-full" :value="old('name_column', 'name')" />
                                <x-input-error :messages="$errors->get('name_column')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">Näiteks: name, nimi, company_name</p>
                            </div>

                            <!-- Subject -->
                            <div>
                                <x-input-label for="subject" :value="__('E-maili teema')" />
                                <x-text-input id="subject" name="subject" type="text" 
                                    class="mt-1 block w-full" :value="old('subject')" required />
                                <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                            </div>

                            <!-- Subject RU -->
                            <div>
                                <x-input-label for="subject_ru" :value="__('E-maili teema (vene keel, valikuline)')" />
                                <x-text-input id="subject_ru" name="subject_ru" type="text" 
                                    class="mt-1 block w-full" :value="old('subject_ru')" />
                                <x-input-error :messages="$errors->get('subject_ru')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">Kasutatakse .ru domeenide jaoks</p>
                            </div>

                            <!-- Message -->
                            <div class="md:col-span-2">
                                <x-input-label for="message" :value="__('E-maili sisu (HTML)')" />
                                <textarea id="message" name="message" rows="8" required
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">
                                    Kasuta HTML märgistust. Muutujad: {company_name}, {recipient_name}
                                </p>
                                
                                <!-- HTML Preview -->
                                <div class="mt-4" id="previewContainer">
                                    <button type="button" onclick="togglePreview('previewContainer')" 
                                        class="text-sm text-blue-600 hover:text-blue-800">Näita</button>
                                    <div id="previewContent" style="display: none;" 
                                        class="mt-2 p-4 border rounded bg-gray-50 max-h-60 overflow-y-auto">
                                        <em class="text-gray-500">Sisesta HTML sisu üleval, et näha eelvaadet...</em>
                                    </div>
                                </div>
                            </div>

                            <!-- Message RU -->
                            <div class="md:col-span-2">
                                <x-input-label for="message_ru" :value="__('E-maili sisu vene keeles (valikuline)')" />
                                <textarea id="message_ru" name="message_ru" rows="8"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('message_ru') }}</textarea>
                                <x-input-error :messages="$errors->get('message_ru')" class="mt-2" />
                                <p class="mt-1 text-sm text-gray-500">
                                    Kasutatakse .ru domeenide jaoks. Muutujad: {company_name}, {recipient_name}
                                </p>
                                
                                <!-- HTML Preview RU -->
                                <div class="mt-4" id="previewContainerRu">
                                    <button type="button" onclick="togglePreview('previewContainerRu')" 
                                        class="text-sm text-blue-600 hover:text-blue-800">Näita</button>
                                    <div id="previewContentRu" style="display: none;" 
                                        class="mt-2 p-4 border rounded bg-gray-50 max-h-60 overflow-y-auto">
                                        <em class="text-gray-500">Sisesta HTML sisu üleval, et näha eelvaadet...</em>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">
                                            Kuidas see töötab
                                        </h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>1. Laadi üles CSV fail e-mailidega</p>
                                            <p>2. Kirjuta kirja sisu (eesti ja vene keeles)</p>
                                            <p>3. Vajuta "Loo kampaania" - kõik kirjad lähevad järjekorda</p>
                                            <p>4. Vajuta "Alusta saatmist" - kirjad saadetakse 15-sekundilise intervalliga</p>
                                            <p><strong>🛡️ Cooldown kaitse:</strong> Samale e-mailile ei saadeta kirja {{ env('EMAIL_COOLDOWN_DAYS', 14) }} päeva jooksul</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('email-campaigns.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                            <x-primary-button>
                                {{ __('Loo Kampaania') }}
                            </x-primary-button>
                        </div>
                    </form>
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

        // Initialize preview functionality
        document.addEventListener('DOMContentLoaded', function() {
            const messageTextarea = document.getElementById('message');
            const messageRuTextarea = document.getElementById('message_ru');
            
            messageTextarea.addEventListener('input', function() {
                updatePreview('message', 'previewContent');
            });
            
            messageTextarea.addEventListener('paste', function() {
                setTimeout(() => updatePreview('message', 'previewContent'), 10);
            });
            
            messageRuTextarea.addEventListener('input', function() {
                updatePreview('message_ru', 'previewContentRu');
            });
            
            messageRuTextarea.addEventListener('paste', function() {
                setTimeout(() => updatePreview('message_ru', 'previewContentRu'), 10);
            });
        });
    </script>
</x-app-layout>
