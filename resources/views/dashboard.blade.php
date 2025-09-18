<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CRM Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Customers</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['customers'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm6 0a2 2 0 104 0 2 2 0 00-4 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Companies</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['companies'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Active Deals</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['deals'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Pending Tasks</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['tasks'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Deal Value</p>
                                <p class="text-2xl font-semibold text-gray-900">${{ number_format($stats['total_deal_value'], 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Won Deals</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['won_deals'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Customers -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Customers</h3>
                        @if($recent_customers->count() > 0)
                            <div class="space-y-3">
                                @foreach($recent_customers as $customer)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $customer->full_name }}</p>
                                            <p class="text-sm text-gray-500">{{ $customer->email }}</p>
                                            @if($customer->company)
                                                <p class="text-xs text-gray-400">{{ $customer->company->name }}</p>
                                            @endif
                                        </div>
                                        <a href="{{ route('customers.show', $customer) }}" class="text-blue-600 hover:text-blue-800">
                                            View
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('customers.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View all customers →
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500">No customers yet. <a href="{{ route('customers.create') }}" class="text-blue-600 hover:text-blue-800">Create your first customer</a></p>
                        @endif
                    </div>
                </div>

                <!-- Upcoming Tasks -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Upcoming Tasks</h3>
                        @if($upcoming_tasks->count() > 0)
                            <div class="space-y-3">
                                @foreach($upcoming_tasks as $task)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $task->title }}</p>
                                            <p class="text-sm text-gray-500">{{ $task->type }} - {{ ucfirst($task->priority) }} priority</p>
                                            @if($task->due_date)
                                                <p class="text-xs text-gray-400">Due: {{ $task->due_date->format('M j, Y') }}</p>
                                            @endif
                                        </div>
                                        <a href="{{ route('tasks.show', $task) }}" class="text-blue-600 hover:text-blue-800">
                                            View
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('tasks.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View all tasks →
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500">No upcoming tasks. <a href="{{ route('tasks.create') }}" class="text-blue-600 hover:text-blue-800">Create your first task</a></p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <a href="{{ route('customers.create') }}" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <svg class="w-8 h-8 text-blue-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"></path>
                            </svg>
                            <span class="text-sm font-medium text-blue-600">Add Customer</span>
                        </a>
                        
                        <a href="{{ route('companies.create') }}" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <svg class="w-8 h-8 text-green-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm6 0a2 2 0 104 0 2 2 0 00-4 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-medium text-green-600">Add Company</span>
                        </a>
                        
                        <a href="{{ route('deals.create') }}" class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                            <svg class="w-8 h-8 text-yellow-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                            </svg>
                            <span class="text-sm font-medium text-yellow-600">Add Deal</span>
                        </a>
                        
                        <a href="{{ route('contacts.create') }}" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <svg class="w-8 h-8 text-purple-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-medium text-purple-600">Add Contact</span>
                        </a>
                        
                        <a href="{{ route('tasks.create') }}" class="flex flex-col items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                            <svg class="w-8 h-8 text-indigo-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-medium text-indigo-600">Add Task</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
