<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Staff Member</h2>
                <p class="mt-1 text-sm text-gray-500">Update staff information and credentials.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="staff_id" value="Staff ID" />
                            <x-text-input id="staff_id" type="text" value="{{ $user->staff_id }}" class="mt-1 block w-full bg-gray-50" disabled />
                            <p class="mt-1 text-xs text-gray-500">Staff ID cannot be changed</p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="name" value="Staff Name *" />
                            <x-text-input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="mt-1 block w-full" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="email" value="Email Address *" />
                            <x-text-input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone Number" />
                            <x-text-input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="mt-1 block w-full" placeholder="e.g., +91XXXXXXXXXX" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500">Only active staff can log in to the system</p>
                        </div>

                        <div class="md:col-span-2 border-t border-gray-200 pt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Password</h3>
                            <p class="text-xs text-gray-500 mb-3">Leave password fields blank to keep the current password</p>
                            
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="password" value="New Password" />
                                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                    <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
                                </div>

                                <div>
                                    <x-input-label for="password_confirmation" value="Confirm New Password" />
                                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button>Update Staff Member</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

