<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Uus Ülesanne') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('tasks.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Title -->
                            <div>
                                <x-input-label for="title" :value="__('Pealkiri')" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Description -->
                            <div>
                                <x-input-label for="description" :value="__('Kirjeldus')" />
                                <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <!-- Type -->
                            <div>
                                <x-input-label for="type" :value="__('Tüüp')" />
                                <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="call" {{ old('type') == 'call' ? 'selected' : '' }}>Kõne</option>
                                    <option value="email" {{ old('type') == 'email' ? 'selected' : '' }}>E-mail</option>
                                    <option value="meeting" {{ old('type') == 'meeting' ? 'selected' : '' }}>Kohtumine</option>
                                    <option value="follow_up" {{ old('type') == 'follow_up' ? 'selected' : '' }}>Järelkontroll</option>
                                    <option value="development" {{ old('type') == 'development' ? 'selected' : '' }}>Arendus</option>
                                    <option value="bug_fix" {{ old('type') == 'bug_fix' ? 'selected' : '' }}>Parandus</option>
                                    <option value="content_creation" {{ old('type') == 'content_creation' ? 'selected' : '' }}>Sisu lisamine</option>
                                    <option value="proposal_creation" {{ old('type') == 'proposal_creation' ? 'selected' : '' }}>Pakkumise koostamine</option>
                                    <option value="testing" {{ old('type') == 'testing' ? 'selected' : '' }}>Testimine</option>
                                    <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Muu</option>
                                </select>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>

                            <!-- Priority -->
                            <div>
                                <x-input-label for="priority" :value="__('Prioriteet')" />
                                <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Madal</option>
                                    <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Keskmine</option>
                                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Kõrge</option>
                                    <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Kiire</option>
                                </select>
                                <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('Staatus')" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Ootel</option>
                                    <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>Töös</option>
                                    <option value="needs_testing" {{ old('status') == 'needs_testing' ? 'selected' : '' }}>Vajab testimist</option>
                                    <option value="needs_clarification" {{ old('status') == 'needs_clarification' ? 'selected' : '' }}>Vajab täpsustust</option>
                                    <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Valmis</option>
                                    <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Tühistatud</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <!-- Due Date -->
                            <div>
                                <x-input-label for="due_date" :value="__('Tähtaeg')" />
                                <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            </div>

                            <!-- Customer -->
                            <div>
                                <x-input-label for="customer_id" :value="__('Klient (valikuline)')" />
                                <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Vali klient...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <!-- Company -->
                            <div>
                                <x-input-label for="company_id" :value="__('Ettevõte *')" />
                                <select id="company_id" name="company_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali ettevõte...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('company_id')" class="mt-2" />
                            </div>

                            <!-- Assignee -->
                            <div>
                                <x-input-label for="assignee_id" :value="__('Vastutaja *')" />
                                <select id="assignee_id" name="assignee_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali vastutaja...</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('assignee_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('assignee_id')" class="mt-2" />
                            </div>

                            <!-- Deal -->
                            <div>
                                <x-input-label for="deal_id" :value="__('Tehing *')" />
                                <select id="deal_id" name="deal_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Vali tehing...</option>
                                    @foreach($deals as $deal)
                                        <option value="{{ $deal->id }}" {{ old('deal_id') == $deal->id ? 'selected' : '' }}>
                                            {{ $deal->title }} (€{{ number_format($deal->value, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('deal_id')" class="mt-2" />
                            </div>


                            <!-- Price -->
                            <div>
                                <x-input-label for="price" :value="__('Hind (€)')" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price')" required />
                                <x-input-error :messages="$errors->get('price')" class="mt-2" />
                            </div>

                            <!-- Notes -->
                            <div>
                                <x-input-label for="notes" :value="__('Märkused')" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-4">
                            <a href="{{ route('tasks.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Tühista
                            </a>
                         
                            <x-primary-button>
                                {{ __('Salvesta') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Rich Text Editor (TinyMCE) -->
    <style>
        /* Ensure TinyMCE area is always interactive */
        .tox .tox-edit-area__overlay { display: none !important; }
        .tox .tox-edit-area__iframe { pointer-events: auto !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        window.addEventListener('load', function() {
            if (window.tinymce) {
                tinymce.remove();
            }
            const commonOptions = {
                plugins: 'lists link code fullscreen table',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link table | code | fullscreen',
                menubar: false,
                branding: false,
                statusbar: true,
                height: 320,
                convert_urls: false,
                skin: 'oxide',
                content_css: 'default',
                content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
                readonly: false,
                promotion: false,
                license_key: 'gpl',
            };

            if (document.querySelector('textarea#description')) {
                tinymce.init({
                    selector: 'textarea#description',
                    ...commonOptions,
                    setup: (editor) => {
                        editor.on('init', () => {
                            try { editor.getBody().setAttribute('contenteditable', true); } catch (e) {}
                        });
                    }
                });
            }
            if (document.querySelector('textarea#notes')) {
                tinymce.init({
                    selector: 'textarea#notes',
                    ...commonOptions,
                });
            }
        });
    </script>
</x-app-layout>
