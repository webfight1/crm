@props(['active' => false, 'label'])

<div class="relative flex items-center" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open" class="h-full flex">
        <button class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ $active 
            ? 'border-indigo-400 text-gray-900 focus:outline-none focus:border-indigo-700' 
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300' }}">
            {{ $label }}
            <svg class="ml-1 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute z-50 mt-1 w-48 rounded-md shadow-lg origin-top-left left-0"
         style="display: none; top: 100%;">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-white">
            {{ $slot }}
        </div>
    </div>
</div>
