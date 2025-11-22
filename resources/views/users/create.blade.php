<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Staff Member</h2>
                <p class="mt-1 text-sm text-gray-500">Create a new staff account with login credentials.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Staff Name *" />
                            <x-text-input id="name" name="name" type="text" value="{{ old('name') }}" class="mt-1 block w-full" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="email" value="Email Address *" />
                            <x-text-input id="email" name="email" type="email" value="{{ old('email') }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">This will be used as the login email</p>
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone Number" />
                            <x-text-input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="mt-1 block w-full" placeholder="e.g., +91XXXXXXXXXX" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">Leave blank to auto-generate a random password. Minimum 8 characters if provided.</p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="password_confirmation" value="Confirm Password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500">Only active staff can log in to the system</p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-800">
                            <strong>Note:</strong> A unique Staff ID will be automatically generated. The login credentials (email and password) will be displayed after creation for you to share with the staff member.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button>Create Staff Member</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

