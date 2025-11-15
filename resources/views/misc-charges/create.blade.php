<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Miscellaneous Charge</h2>
                <p class="mt-1 text-sm text-gray-500">Add a miscellaneous charge. You can link it to a course (applies to all students in that course) or make it global (available for selection when enrolling students).</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('misc-charges.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="course_id" value="Course" />
                            <select id="course_id" name="course_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Global Charge (No Course) --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('course_id')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Select a course to apply to all students in that course, or leave blank for a global charge that can be selected when enrolling students.</p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="label" value="Charge Item *" />
                            <x-text-input id="label" name="label" type="text" value="{{ old('label') }}" class="mt-1 block w-full" placeholder="e.g., Jacket, Cap, Hoodie, Lab Kit" required />
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Name of the item or service</p>
                        </div>

                        <div>
                            <x-input-label for="amount" value="Amount (₹) *" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">₹</span>
                                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" 
                                       class="block w-full rounded-lg border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due Date" />
                            <x-text-input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Optional due date for this charge</p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <strong>Note:</strong> 
                                    <span id="charge-note">
                                        If a course is selected, this charge will be automatically added to all students currently enrolled in that course. Future students enrolled in the course will also receive this charge. If no course is selected, this will be a global charge available for selection when enrolling new students.
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('misc-charges.index') }}" class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button>Save Charge</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

