<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ asset('images/logo.svg') }}" class="block h-8 w-8" alt="Logo" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Töölaud') }}
                    </x-nav-link>

                    <x-nav-dropdown :active="request()->routeIs(['companies.*', 'customers.*'])" :label="__('Ettevõtted')">
                        <x-nav-dropdown-link :href="route('companies.index')" :active="request()->routeIs('companies.*')">
                            {{ __('Ettevõtted') }}
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                            {{ __('Kliendid') }}
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-dropdown :active="request()->routeIs(['deals.*', 'quotations.*'])" :label="__('Tehingud')">
                        <x-nav-dropdown-link :href="route('deals.index')" :active="request()->routeIs('deals.*')">
                            {{ __('Tehingud') }}
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('quotations.index')" :active="request()->routeIs('quotations.*')">
                            {{ __('Pakkumised') }}
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                        {{ __('Kontaktid') }}
                    </x-nav-link>

                    <x-nav-dropdown :active="request()->routeIs('tasks.*')" :label="__('Ülesanded')">
                        <x-nav-dropdown-link :href="route('tasks.index')" :active="request()->routeIs('tasks.index') && !request()->has('favorite')">
                            {{ __('Kõik ülesanded') }}
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('tasks.index', ['favorite' => 1])" :active="request()->has('favorite')">
                            {{ __('Tärniga ülesanded') }}
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>

                    <x-nav-link :href="route('calendar.index')" :active="request()->routeIs('calendar.*')">
                        {{ __('Kalender') }}
                    </x-nav-link>

                    <x-nav-dropdown :active="request()->routeIs(['email-campaigns.*', 'email-logs.*'])" :label="__('E-post')">
                        <x-nav-dropdown-link :href="route('email-campaigns.index')" :active="request()->routeIs('email-campaigns.*')">
                            {{ __('Kampaaniad') }}
                        </x-nav-dropdown-link>
                        <x-nav-dropdown-link :href="route('email-logs.index')" :active="request()->routeIs('email-logs.*')">
                            {{ __('Logid') }}
                        </x-nav-dropdown-link>
                    </x-nav-dropdown>
                </div>
            </div>

            <!-- Active Timer -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div id="active-timer" class="mr-4 px-4 py-2 bg-green-100 text-green-800 rounded-md hidden items-center space-x-2">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span id="timer-task-title" class="font-medium"></span>
                    </span>
                    <span id="timer-duration" class="font-bold"></span>
                    <button id="stop-timer" class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Stop
                    </button>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden relative sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                            <x-dropdown-link :href="route('settings.edit')">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ __('Seaded') }}
                                </div>
                            </x-dropdown-link>

                            <x-dropdown-link :href="route('profile.edit')">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ __('Profiil') }}
                                </div>
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    {{ __('Logi välja') }}
                                </div>
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

        

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <!-- Ettevõtted -->
            <div class="pl-3">
                <div class="font-medium text-base text-gray-800 mb-1">{{ __('Ettevõtted') }}</div>
                <x-responsive-nav-link :href="route('companies.index')" :active="request()->routeIs('companies.*')">
                    {{ __('Ettevõtted') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                    {{ __('Kliendid') }}
                </x-responsive-nav-link>
            </div>

            <!-- Tehingud -->
            <div class="pl-3">
                <div class="font-medium text-base text-gray-800 mb-1">{{ __('Tehingud') }}</div>
                <x-responsive-nav-link :href="route('deals.index')" :active="request()->routeIs('deals.*')">
                    {{ __('Tehingud') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('quotations.index')" :active="request()->routeIs('quotations.*')">
                    {{ __('Pakkumised') }}
                </x-responsive-nav-link>
            </div>

            <x-responsive-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                {{ __('Kontaktid') }}
            </x-responsive-nav-link>

            <!-- Ülesanded -->
            <div class="pl-3">
                <div class="font-medium text-base text-gray-800 mb-1">{{ __('Ülesanded') }}</div>
                <x-responsive-nav-link :href="route('tasks.index')" :active="request()->routeIs('tasks.index') && !request()->has('favorite')">
                    {{ __('Kõik ülesanded') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tasks.index', ['favorite' => 1])" :active="request()->has('favorite')">
                    {{ __('Tärniga ülesanded') }}
                </x-responsive-nav-link>
            </div>

            <!-- E-post -->
            <div class="pl-3">
                <div class="font-medium text-base text-gray-800 mb-1">{{ __('E-post') }}</div>
                <x-responsive-nav-link :href="route('email-campaigns.index')" :active="request()->routeIs('email-campaigns.*')">
                    {{ __('Kampaaniad') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('email-logs.index')" :active="request()->routeIs('email-logs.*')">
                    {{ __('Logid') }}
                </x-responsive-nav-link>
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
