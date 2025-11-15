<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Course</h2>
                <p class="mt-1 text-sm text-gray-500">Add a new course offered by the institution.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('courses.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Course Name *" />
                            <x-text-input id="name" name="name" type="text" value="{{ old('name') }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="code" value="Course Code" />
                            <x-text-input id="code" name="code" type="text" value="{{ old('code') }}" class="mt-1 block w-full" placeholder="e.g., CC, FC, IP" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Unique code for this course (optional)</p>
                        </div>

                        <div>
                            <x-input-label for="duration_months" value="Duration (months)" />
                            <x-text-input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months') }}" class="mt-1 block w-full" placeholder="e.g., 6, 12" />
                            <x-input-error :messages="$errors->get('duration_months')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Course duration in months (optional)</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500">Only active courses will appear in student enrollment forms</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('courses.index') }}" class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button>Save Course</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

