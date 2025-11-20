<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fees Management CRM</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
            @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
<body class="font-sans antialiased bg-gradient-to-br from-indigo-50 via-white to-blue-50">
    <div class="min-h-screen flex flex-col">
        <!-- Navigation -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-blue-600 bg-clip-text text-transparent">
                                Fees CRM
                            </h1>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                    @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-700 hover:text-indigo-600 px-4 py-2 rounded-lg hover:bg-indigo-50 transition">
                            Dashboard
                        </a>
                    @else
                            <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600 px-4 py-2 rounded-lg hover:bg-indigo-50 transition">
                            Log in
                        </a>
                        @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition shadow-md">
                                Register
                            </a>
                        @endif
                    @endauth
                    </div>
                </div>
            </div>
                </nav>

        <!-- Hero Section -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6">
                        Fees Management
                        <span class="bg-gradient-to-r from-indigo-600 to-blue-600 bg-clip-text text-transparent">CRM System</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        Streamline your fee collection process with our comprehensive CRM solution. 
                        Manage students, track payments, automate reminders, and generate reports all in one place.
                    </p>
                    <div class="flex justify-center space-x-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg font-semibold text-lg">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg font-semibold text-lg">
                                Get Started
                            </a>
                            <a href="{{ route('register') }}" class="bg-white text-indigo-600 border-2 border-indigo-600 px-8 py-3 rounded-lg hover:bg-indigo-50 transition shadow-lg font-semibold text-lg">
                                Sign Up
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Features Grid -->
                <div class="mt-20 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Student Management</h3>
                        <p class="text-gray-600">Efficiently manage student profiles, enrollments, and fee structures with an intuitive interface.</p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Payment Processing</h3>
                        <p class="text-gray-600">Record payments, track installments, and manage credit balances with automated calculations.</p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Automated Reminders</h3>
                        <p class="text-gray-600">Automatically send payment reminders and apply penalties for overdue installments.</p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Reschedule & Discounts</h3>
                        <p class="text-gray-600">Handle reschedule requests and discount approvals with a streamlined workflow.</p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Analytics & Reports</h3>
                        <p class="text-gray-600">Get insights with comprehensive dashboards, tax breakdowns, and financial reports.</p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Secure & Reliable</h3>
                        <p class="text-gray-600">Role-based access control, audit logging, and secure payment processing.</p>
                    </div>
                </div>
                </div>
            </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="text-center text-gray-600">
                    <p>&copy; {{ date('Y') }} Fees Management CRM. All rights reserved.</p>
                </div>
            </div>
        </footer>
        </div>
    </body>
</html>
