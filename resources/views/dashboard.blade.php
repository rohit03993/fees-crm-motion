<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                <p class="mt-1 text-sm text-gray-600">Tax & Safe Ratio Overview</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="flex gap-2">
                <input type="date" name="start_date" value="{{ $startDate }}" 
                    class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="date" name="end_date" value="{{ $endDate }}" 
                    class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit" 
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Filter
                </button>
                @if($startDate || $endDate)
                    <a href="{{ route('dashboard') }}" 
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Clear
                    </a>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 p-5 text-white shadow-lg">
                <p class="text-sm font-medium text-blue-100">Total Students</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($quickStats['total_students']) }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 text-white shadow-lg">
                <p class="text-sm font-medium text-emerald-100">Total Payments</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($quickStats['total_payments']) }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 p-5 text-white shadow-lg">
                <p class="text-sm font-medium text-purple-100">Total Collection</p>
                <p class="mt-2 text-3xl font-bold">₹{{ number_format($quickStats['total_collection'], 2) }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 p-5 text-white shadow-lg">
                <p class="text-sm font-medium text-amber-100">Pending Reschedules</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($quickStats['pending_reschedules']) }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-rose-500 to-rose-600 p-5 text-white shadow-lg">
                <p class="text-sm font-medium text-rose-100">Pending Discounts</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($quickStats['pending_discounts']) }}</p>
            </div>
        </div>

        <!-- Safe Ratio Alert -->
        @if($taxData['safe_ratio']['is_exceeded'])
            <div class="rounded-2xl border-2 border-rose-500 bg-rose-50 p-6 shadow-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-semibold text-rose-900">Safe Ratio Exceeded</h3>
                        <div class="mt-2 text-sm text-rose-700">
                            <p>
                                The online base payment ratio has exceeded the safe threshold of 
                                <strong>{{ $taxData['safe_ratio']['threshold_percentage'] }}%</strong>.
                            </p>
                            @if($taxData['safe_ratio']['percentage'] !== null)
                                <p class="mt-1">
                                    Current ratio: <strong>{{ $taxData['safe_ratio']['percentage'] }}%</strong>
                                    (Online Base: ₹{{ number_format($taxData['safe_ratio']['online_base'], 2) }} / 
                                    Cash Base: ₹{{ number_format($taxData['safe_ratio']['cash_base'], 2) }})
                                </p>
                            @else
                                <p class="mt-1">
                                    Cash base is ₹0 while online base is ₹{{ number_format($taxData['safe_ratio']['online_base'], 2) }}.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Cash vs Online Breakdown -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Cash Payments -->
            <div class="rounded-2xl bg-white p-6 shadow-lg border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Cash Payments</h3>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                        {{ $taxData['cash']['count'] }} payments
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4">
                        <span class="text-sm font-medium text-gray-600">Base Amount</span>
                        <span class="text-lg font-semibold text-gray-900">₹{{ number_format($taxData['cash']['base'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4">
                        <span class="text-sm font-medium text-gray-600">GST Amount</span>
                        <span class="text-lg font-semibold text-gray-900">₹{{ number_format($taxData['cash']['gst'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gradient-to-r from-gray-600 to-gray-700 p-4 text-white">
                        <span class="text-sm font-medium">Total Received</span>
                        <span class="text-xl font-bold">₹{{ number_format($taxData['cash']['total'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Online Payments -->
            <div class="rounded-2xl bg-white p-6 shadow-lg border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Online Payments</h3>
                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-medium text-indigo-700">
                        {{ $taxData['online']['count'] }} payments
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-lg bg-indigo-50 p-4">
                        <span class="text-sm font-medium text-gray-600">Base Amount</span>
                        <span class="text-lg font-semibold text-gray-900">₹{{ number_format($taxData['online']['base'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-indigo-50 p-4">
                        <span class="text-sm font-medium text-gray-600">GST Amount</span>
                        <span class="text-lg font-semibold text-gray-900">₹{{ number_format($taxData['online']['gst'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-700 p-4 text-white">
                        <span class="text-sm font-medium">Total Received</span>
                        <span class="text-xl font-bold">₹{{ number_format($taxData['online']['total'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safe Ratio Indicator -->
        <div class="rounded-2xl bg-white p-6 shadow-lg border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Safe Ratio Monitor</h3>
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $taxData['safe_ratio']['is_exceeded'] ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $taxData['safe_ratio']['is_exceeded'] ? 'Exceeded' : 'Within Limit' }}
                </span>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-600">Online Base / Cash Base</span>
                        @if($taxData['safe_ratio']['percentage'] !== null)
                            <span class="text-lg font-semibold text-gray-900">{{ $taxData['safe_ratio']['percentage'] }}%</span>
                        @else
                            <span class="text-lg font-semibold text-rose-600">N/A (No Cash Base)</span>
                        @endif
                    </div>
                    @if($taxData['safe_ratio']['cash_base'] > 0)
                        <div class="relative h-4 w-full overflow-hidden rounded-full bg-gray-200">
                            @php
                                $progress = min(100, ($taxData['safe_ratio']['percentage'] ?? 0));
                                $thresholdPercent = ($taxData['safe_ratio']['threshold'] * 100);
                                $barColor = $taxData['safe_ratio']['is_exceeded'] ? 'bg-rose-500' : 'bg-emerald-500';
                            @endphp
                            <div class="h-full {{ $barColor }} transition-all duration-500" style="width: {{ $progress }}%"></div>
                            <div class="absolute left-0 top-0 h-full w-full">
                                <div class="absolute top-0 h-full w-0.5 bg-yellow-500" style="left: {{ $thresholdPercent }}%"></div>
                            </div>
                        </div>
                        <div class="mt-1 flex justify-between text-xs text-gray-500">
                            <span>0%</span>
                            <span>Threshold: {{ $taxData['safe_ratio']['threshold_percentage'] }}%</span>
                            <span>100%</span>
                        </div>
                    @else
                        <div class="h-4 w-full rounded-full bg-gray-200">
                            <div class="h-full w-full bg-rose-500"></div>
                        </div>
                        <p class="mt-1 text-xs text-rose-600">No cash base available. All payments are online.</p>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs text-gray-600">Cash Base</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">₹{{ number_format($taxData['safe_ratio']['cash_base'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-3">
                        <p class="text-xs text-gray-600">Online Base</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">₹{{ number_format($taxData['safe_ratio']['online_base'], 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Totals -->
        <div class="rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 p-6 text-white shadow-lg">
            <h3 class="mb-4 text-lg font-semibold">Overall Totals</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-slate-300">Base Amount</p>
                    <p class="mt-1 text-2xl font-bold">₹{{ number_format($taxData['totals']['base'], 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-300">GST Amount</p>
                    <p class="mt-1 text-2xl font-bold">₹{{ number_format($taxData['totals']['gst'], 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-300">Total Received</p>
                    <p class="mt-1 text-2xl font-bold">₹{{ number_format($taxData['totals']['total'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown by Mode -->
        @if($taxData['payment_breakdown']->count() > 0)
            <div class="rounded-2xl bg-white p-6 shadow-lg border border-gray-100">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Payment Breakdown by Mode</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Payment Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Base Amount</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">GST Amount</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Count</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($taxData['payment_breakdown'] as $breakdown)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 capitalize">
                                        {{ str_replace('_', ' ', $breakdown['mode']) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700">
                                        ₹{{ number_format($breakdown['base'], 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700">
                                        ₹{{ number_format($breakdown['gst'], 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900">
                                        ₹{{ number_format($breakdown['total'], 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700">
                                        {{ $breakdown['count'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $breakdown['is_online'] ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $breakdown['is_online'] ? 'Online' : 'Cash' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Daily Collection Chart -->
        @if($taxData['daily_data']->count() > 0)
            <div class="rounded-2xl bg-white p-6 shadow-lg border border-gray-100">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Daily Collection Trend</h3>
                <div class="space-y-2">
                    @php
                        $maxTotal = $taxData['daily_data']->max('total') ?: 1;
                    @endphp
                    @foreach($taxData['daily_data']->take(14) as $day)
                        <div class="flex items-center gap-3">
                            <div class="w-24 text-xs text-gray-600">
                                {{ \Carbon\Carbon::parse($day['date'])->format('M d') }}
                            </div>
                            <div class="flex-1">
                                <div class="flex h-8 items-center gap-1">
                                    @if($day['cash_total'] > 0)
                                        <div class="rounded-l bg-gray-400 text-xs text-white" 
                                            style="width: {{ ($day['cash_total'] / $maxTotal) * 100 }}%">
                                            <span class="ml-2">{{ $day['cash_total'] > 0 ? '₹' . number_format($day['cash_total']) : '' }}</span>
                                        </div>
                                    @endif
                                    @if($day['online_total'] > 0)
                                        <div class="rounded-r bg-indigo-500 text-xs text-white" 
                                            style="width: {{ ($day['online_total'] / $maxTotal) * 100 }}%">
                                            <span class="ml-2">{{ $day['online_total'] > 0 ? '₹' . number_format($day['online_total']) : '' }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="w-24 text-right text-sm font-semibold text-gray-900">
                                ₹{{ number_format($day['total'], 0) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center justify-center gap-4 text-xs text-gray-600">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded bg-gray-400"></div>
                        <span>Cash</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded bg-indigo-500"></div>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
