<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Miscellaneous Charges</h2>
                <p class="mt-1 text-sm text-gray-500">Manage miscellaneous charges. Add course-specific charges (applies to all students in a course) or global charges (available when enrolling students).</p>
            </div>
            <a href="{{ route('misc-charges.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Add Charge
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($chargesByCourse->isEmpty() && $globalCharges->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6">
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No miscellaneous charges</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by adding a charge (course-specific or global).</p>
                        <div class="mt-6">
                            <a href="{{ route('misc-charges.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                Add Charge
                            </a>
                        </div>
                    </div>
                </div>
            @else
                {{-- Display Global Charges Section --}}
                @if($globalCharges->isNotEmpty())
                    <div class="mb-6 bg-white shadow-sm sm:rounded-lg border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-100 bg-indigo-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Global Charges</h3>
                                    <p class="text-sm text-gray-500 mt-1">{{ $globalCharges->count() }} charge(s) · Total: ₹{{ number_format($globalCharges->sum('amount'), 2) }}</p>
                                    <p class="text-xs text-gray-600 mt-1">Available for selection when enrolling students</p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    Global
                                </span>
                            </div>
                        </div>
                        <div class="p-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($globalCharges as $charge)
                                        <tr class="bg-white hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $charge->label }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">₹{{ number_format($charge->amount, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $charge->due_date ? \Carbon\Carbon::parse($charge->due_date)->format('d M Y') : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="{{ route('misc-charges.edit', $charge) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('misc-charges.destroy', $charge) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this global charge?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Display Course-Specific Charges --}}
                @if($chargesByCourse->isNotEmpty())
                    @foreach($chargesByCourse as $courseId => $charges)
                    @php
                        $course = $charges->first()->course;
                    @endphp
                    <div class="mb-6 bg-white shadow-sm sm:rounded-lg border border-gray-100">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $course->name }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">{{ $charges->count() }} charge(s) · Total: ₹{{ number_format($charges->sum('amount'), 2) }}</p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $course->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $course->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                        <div class="p-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($charges as $charge)
                                        <tr class="bg-white hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $charge->label }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">₹{{ number_format($charge->amount, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $charge->due_date ? \Carbon\Carbon::parse($charge->due_date)->format('d M Y') : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="{{ route('misc-charges.edit', $charge) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('misc-charges.destroy', $charge) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this charge? It will be removed from all students in this course.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</x-app-layout>

