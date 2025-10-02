<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $task->title }}
            </h2>
            <div class="flex space-x-2">
                <button onclick="startTimer({{ $task->id }})" class="flex items-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                    Start Timer
                </button>

                @if(Auth::id() === $task->user_id || Auth::id() === $task->assignee_id)
                    <a href="{{ route('tasks.edit', $task) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Muuda
                    </a>
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
                            onclick="return confirm('Kas oled kindel, et soovid selle ülesande kustutada?')">
                            Kustuta
                        </button>
                    </form>
                @endif
                <a href="{{ route('tasks.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Tagasi
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Pealkiri</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->title }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Prioriteet</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($task->priority === 'high') bg-red-100 text-red-800
                                    @elseif($task->priority === 'medium') bg-yellow-100 text-yellow-800
                                    @else bg-green-100 text-green-800 @endif">
                                    @if($task->priority === 'high') Kõrge
                                    @elseif($task->priority === 'medium') Keskmine
                                    @else Madal @endif
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Staatus</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($task->status === 'completed') bg-green-100 text-green-800
                                    @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    @if($task->status === 'completed') Lõpetatud
                                    @elseif($task->status === 'in_progress') Pooleli
                                    @else Ootel @endif
                                </span>
                            </dd>
                        </div>

                        @if($task->due_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tähtaeg</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="@if($task->due_date->isPast() && $task->status !== 'completed') text-red-600 font-medium @endif">
                                    {{ $task->due_date->format('d.m.Y') }}
                                    @if($task->due_date->isPast() && $task->status !== 'completed')
                                        (Tähtaeg ületatud)
                                    @endif
                                </span>
                            </dd>
                        </div>
                        @endif

                        @if($task->customer)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Klient</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('customers.show', $task->customer) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $task->customer->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($task->company)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ettevõte</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('companies.show', $task->company) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $task->company->name }}
                                </a>
                            </dd>
                        </div>
                        @endif

                        @if($task->deal)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tehing</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('deals.show', $task->deal) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $task->deal->title }} (€{{ number_format($task->deal->value, 2) }})
                                </a>
                            </dd>
                        </div>
                        @endif

                        <!-- Tracked Times -->
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 mb-2">Traktitud ajad</dt>
                            <dd class="mt-1 space-y-2">
                                @forelse($task->timeEntries as $timeEntry)
                                    <div class="flex items-center justify-between text-sm text-gray-900 bg-gray-50 p-2 rounded">
                                        <div class="flex items-center">
                                            <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $timeEntry->start_time->format('d.m.Y H:i') }} - 
                                            @if($timeEntry->end_time)
                                                {{ $timeEntry->end_time->format('H:i') }}
                                            @else
                                                käib
                                            @endif
                                            ({{ floor($timeEntry->duration) }}h {{ round(($timeEntry->duration - floor($timeEntry->duration)) * 60) }}min)
                                        </div>
                                        @if($timeEntry->end_time)
                                            <div class="flex space-x-2">
                                                <a href="{{ route('time-entries.edit', $timeEntry) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </a>
                                                <form method="POST" action="{{ route('time-entries.destroy', $timeEntry) }}" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Kas oled kindel, et soovid selle ajakande kustutada?')">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">Traktitud aegu pole</p>
                                @endforelse

                                @if($task->timeEntries->count() > 0)
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <p class="text-sm font-medium text-gray-900">
                                            Kokku: {{ floor($task->timeEntries->sum('duration')) }}h {{ round(($task->timeEntries->sum('duration') - floor($task->timeEntries->sum('duration'))) * 60) }}min
                                            @if($task->price)
                                                (€{{ number_format($task->timeEntries->sum('duration') * $task->price, 2) }})
                                            @endif
                                        </p>
                                    </div>
                                @endif
                            </dd>
                        </div>

                        @if($task->price)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Hind</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                €{{ number_format($task->price, 2) }}
                            </dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Loodud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->created_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uuendatud</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $task->updated_at->format('d.m.Y H:i') }}</dd>
                        </div>

                        @if($task->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Kirjeldus</dt>
                            <dd class="mt-1 text-sm text-gray-900 prose prose-sm max-w-none">{!! $task->description !!}</dd>
                        </div>
                        @endif

                        <!-- Assignee -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Vastutaja</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($task->assignee)
                                    {{ $task->assignee->name }}
                                @else
                                    Määramata
                                @endif
                            </dd>
                        </div>

                        <!-- Creator -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Looja</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $task->user->name }}
                            </dd>
                        </div>

                        <!-- Comments Section -->
                        <div class="md:col-span-2 mt-8">
                            <dt class="text-lg font-medium text-gray-900 mb-4">Kommentaarid</dt>
                            <dd>
                                <!-- New Comment Form -->
                                <form method="POST" action="{{ route('comments.store', $task) }}" class="mb-6" onsubmit="return validateCommentForm()">
                                    @csrf
                                    <div>
                                        <textarea
                                            id="content"
                                            name="content"
                                            rows="3"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            placeholder="Lisa kommentaar..."
                                        ></textarea>
                                        <!-- TinyMCE -->
                                        <style>
                                            .tox .tox-edit-area__overlay { display: none !important; }
                                            .tox .tox-edit-area__iframe { pointer-events: auto !important; }
                                        </style>
                                        <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
                                        <script>
                                            window.addEventListener('load', function() {
                                                if (window.tinymce) {
                                                    tinymce.remove();
                                                }
                                                tinymce.init({
                                                    selector: 'textarea#content',
                                                    plugins: 'lists link code fullscreen table',
                                                    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link table | code | fullscreen',
                                                    menubar: false,
                                                    branding: false,
                                                    statusbar: true,
                                                    height: 200,
                                                    convert_urls: false,
                                                    skin: 'oxide',
                                                    content_css: 'default',
                                                    content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
                                                    readonly: false,
                                                    promotion: false,
                                                    license_key: 'gpl',
                                                    setup: (editor) => {
                                                        editor.on('init', () => {
                                                            try { editor.getBody().setAttribute('contenteditable', true); } catch (e) {}
                                                        });
                                                    }
                                                });
                                            });
                                        </script>
                                    </div>
                                    <div class="mt-2 flex justify-end">
                                        <x-primary-button>
                                            {{ __('Lisa kommentaar') }}
                                        </x-primary-button>
                                    </div>
                                </form>

                                <!-- Comments List -->
                                <div class="space-y-4">
                                    @forelse($task->comments as $comment)
                                        <div class="{{ Auth::id() === $comment->user_id ? 'bg-blue-50 border-blue-100 ml-20' : 'bg-gray-50 border-gray-100 mr-20' }} rounded-lg p-4 border relative group">
                                            <div class="flex space-x-3">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center justify-between">
                                                        <h3 class="text-sm font-medium {{ Auth::id() === $comment->user_id ? 'text-blue-900' : 'text-gray-900' }}">{{ $comment->user->name }}</h3>
                                                        <div class="flex items-center space-x-4">
                                                            @if(Auth::id() === $comment->user_id)
                                                                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex space-x-2">
                                                                    <a href="{{ route('comments.edit', $comment) }}" class="text-blue-600 hover:text-blue-900">
                                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                        </svg>
                                                                    </a>
                                                                    <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="inline">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Kas oled kindel, et soovid selle kommentaari kustutada?')">
                                                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                                            </svg>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            @endif
                                                            <p class="text-sm {{ Auth::id() === $comment->user_id ? 'text-blue-500' : 'text-gray-500' }}">{{ $comment->created_at->format('d.m.Y H:i') }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="text-sm {{ Auth::id() === $comment->user_id ? 'text-blue-700' : 'text-gray-700' }} prose prose-sm max-w-none">{!! $comment->content !!}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-500">Kommentaare pole veel lisatud.</p>
                                    @endforelse
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Timer functionality
    function startTimer(taskId) {
        fetch(`/time-entries/start/${taskId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const timerElement = document.getElementById('active-timer');
                document.getElementById('timer-task-title').textContent = data.task.title;
                timerElement.classList.remove('hidden');
                window.location.reload(); // Reload to show the timer in header
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    // Comment form validation
    function validateCommentForm() {
        const content = tinymce.get('content').getContent();
        if (!content.trim()) {
            alert('Palun sisesta kommentaar');
            return false;
        }
        return true;
    }

    // Check for active timer on page load
    document.addEventListener('DOMContentLoaded', function() {
        fetch('/time-entries/current')
            .then(response => response.json())
            .then(data => {
                if (data.active_timer) {
                    const timerElement = document.getElementById('active-timer');
                    document.getElementById('timer-task-title').textContent = data.active_timer.task_title;
                    timerElement.classList.remove('hidden');
                }
            });
    });
    </script>
</x-app-layout>
