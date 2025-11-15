<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Courses</h2>
                <p class="mt-1 text-sm text-gray-500">Manage courses offered by the institution.</p>
            </div>
            <a href="{{ route('courses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Add Course
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

            @if(session('error'))
                <div class="mb-6 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Total courses: {{ $courses->total() }}</span>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Name</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Code</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Duration</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Students</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($courses as $course)
                                <tr class="bg-white hover:bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $course->name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $course->code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if($course->duration_months)
                                            {{ $course->duration_months }} {{ $course->duration_months == 1 ? 'month' : 'months' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $course->students_count }}</td>
                                    <td class="px-4 py-3">
                                        @if($course->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('courses.edit', $course) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('courses.destroy', $course) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No courses found. <a href="{{ route('courses.create') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Add your first course</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($courses->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $courses->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

