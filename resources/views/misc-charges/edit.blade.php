<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Miscellaneous Charge</h2>
                <p class="mt-1 text-sm text-gray-500">Update miscellaneous charge details. You can link it to a course or make it global.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('misc-charges.update', $miscCharge) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="course_id" value="Course" />
                            <select id="course_id" name="course_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="" @selected(old('course_id', $miscCharge->course_id) === null)>-- Global Charge (No Course) --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id', $miscCharge->course_id) == $course->id)>{{ $course->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('course_id')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Select a course to apply to all students in that course, or leave blank for a global charge. Changing the course will update charges for all affected students.</p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="label" value="Charge Item *" />
                            <x-text-input id="label" name="label" type="text" value="{{ old('label', $miscCharge->label) }}" class="mt-1 block w-full" placeholder="e.g., Jacket, Cap, Hoodie, Lab Kit" required />
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="amount" value="Amount (₹) *" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">₹</span>
                                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $miscCharge->amount) }}" 
                                       class="block w-full rounded-lg border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due Date" />
                            <x-text-input id="due_date" name="due_date" type="date" value="{{ old('due_date', $miscCharge->due_date?->toDateString()) }}" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-amber-700">
                                    <strong>Important:</strong> 
                                    @if($miscCharge->course_id)
                                        Changes will update <strong>ALL</strong> charges for students in this course, regardless of payment status. This includes pending, paid, and cancelled charges.
                                    @else
                                        Changes will update <strong>ALL</strong> student charges matching this label, regardless of payment status. This charge can also be selected when enrolling new students.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('misc-charges.index') }}" class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button>Update Charge</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

