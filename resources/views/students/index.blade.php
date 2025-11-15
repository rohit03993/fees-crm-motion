<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Students</h2>
                <p class="mt-1 text-sm text-gray-500">Manage student profiles, fee plans, and schedules.</p>
            </div>
            <form action="{{ route('students.create') }}" method="GET">
                <x-primary-button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Student
                </x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 pt-6 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Student roster</span>
                    <form action="{{ route('students.create') }}" method="GET">
                        <x-primary-button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Student
                        </x-primary-button>
                    </form>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">UID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Branch</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Program Fee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Mode</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100 text-sm">
                            @forelse($students as $student)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $student->student_uid }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-gray-900 font-semibold">{{ $student->name }}</div>
                                        @if($student->father_name)
                                            <div class="text-xs text-gray-500">Father: {{ $student->father_name }}</div>
                                        @endif
                                        @if($student->guardian_1_name)
                                            <div class="text-xs text-gray-500">{{ $student->guardian_1_name }} ({{ $student->guardian_1_relation }})</div>
                                            @if($student->guardian_1_whatsapp)
                                                <div class="text-xs text-gray-500">{{ $student->guardian_1_whatsapp }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-700">
                                            {{ $student->course->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $student->branch->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-800">
                                        ₹{{ number_format(optional($student->fee)->total_fee ?? 0, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ optional($student->fee)->payment_mode === 'installments' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ optional($student->fee)->payment_mode === 'installments' ? 'Installments' : 'Full Payment' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $student->admission_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('students.show', $student) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">No students enrolled yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-6">
                        {{ $students->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
