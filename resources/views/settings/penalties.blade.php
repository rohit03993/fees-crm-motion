<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Penalty & Reminder Settings</h2>
                <p class="mt-1 text-sm text-gray-500">Define grace periods, penalty rate, and reminder cadence for overdue installments.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-6">
                <form method="POST" action="{{ route('settings.penalties.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Late Fee Penalties Section -->
                    <div class="border-b border-gray-200 pb-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Late Fee Penalties</h3>
                        <p class="text-sm text-gray-600 mb-4">Configure automatic penalties for overdue installment payments.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="grace_days" value="Grace Period (days)" />
                                <x-text-input id="grace_days" name="grace_days" type="number" min="0" max="60" class="mt-1 block w-full" value="{{ old('grace_days', $settings['grace_days']) }}" required />
                                <x-input-error :messages="$errors->get('grace_days')" class="mt-2" />
                                <p class="mt-2 text-xs text-gray-500">Penalties start after the grace period ends.</p>
                            </div>
                            <div>
                                <x-input-label for="rate_percent" value="Penalty Rate (% per day)" />
                                <x-text-input id="rate_percent" name="rate_percent" type="number" step="0.1" min="0" max="100" class="mt-1 block w-full" value="{{ old('rate_percent', $settings['rate_percent']) }}" required />
                                <x-input-error :messages="$errors->get('rate_percent')" class="mt-2" />
                                <p class="mt-2 text-xs text-gray-500">Percentage applied on outstanding amount for each delayed day.</p>
                            </div>
                        </div>
                    </div>

                    <!-- GST Penalty for Online Payments Section -->
                    <div class="border-b border-gray-200 pb-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">GST Penalty for Online Payments</h3>
                        <p class="text-sm text-gray-600 mb-4">Configure GST percentage applied when online payment allowance is exceeded.</p>
                        
                        <div class="md:w-1/2">
                            <x-input-label for="gst_percentage" value="GST Percentage (%)" />
                            <x-text-input id="gst_percentage" name="gst_percentage" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" value="{{ old('gst_percentage', $settings['gst_percentage']) }}" required />
                            <x-input-error :messages="$errors->get('gst_percentage')" class="mt-2" />
                            <p class="mt-2 text-xs text-gray-500">GST percentage applied on the excess amount when online allowance is exceeded. Only applies to tuition fee payments.</p>
                        </div>
                    </div>

                    <!-- Reminder Settings Section -->
                    <div class="pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reminder Settings</h3>
                        <p class="text-sm text-gray-600 mb-4">Configure reminder frequency for overdue installments.</p>
                        
                        <div class="md:w-1/2">
                            <x-input-label for="reminder_cadence" value="Reminder Cadence (days)" />
                            <x-text-input id="reminder_cadence" name="reminder_cadence" type="number" min="1" max="30" class="mt-1 block w-full" value="{{ old('reminder_cadence', $settings['reminder_cadence']) }}" required />
                            <x-input-error :messages="$errors->get('reminder_cadence')" class="mt-2" />
                            <p class="mt-2 text-xs text-gray-500">How often to queue reminders for overdue installments.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <x-primary-button>Save Settings</x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Clear All Student Data Section -->
            <div class="mt-8 bg-red-50 border-2 border-red-200 shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-semibold text-red-900">Clear All Student Data</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p class="font-medium">Warning: This action cannot be undone!</p>
                            <p class="mt-1">This will permanently delete all students and all related data including:</p>
                            <ul class="mt-2 list-disc list-inside space-y-1">
                                <li>All student records ({{ number_format($studentCount ?? 0) }} students)</li>
                                <li>All payment history</li>
                                <li>All installment schedules</li>
                                <li>All penalties, reminders, and reschedules</li>
                                <li>All discounts and miscellaneous charges</li>
                                <li>All WhatsApp logs</li>
                            </ul>
                            <p class="mt-2 font-semibold">This action is irreversible and should only be used to clear test data.</p>
                        </div>
                        <div class="mt-4">
                            <form method="POST" action="{{ route('settings.clear-students') }}" id="clear-students-form" onsubmit="return confirmClearAll()">
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Clear All Student Data
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmClearAll() {
            const studentCount = {{ $studentCount ?? 0 }};
            const message = `Are you absolutely sure you want to delete ALL ${studentCount} students and all related data?\n\n` +
                          `This action is PERMANENT and CANNOT be undone!\n\n` +
                          `Type "DELETE ALL" in the next prompt to confirm.`;
            
            const confirmation = prompt(message);
            
            if (confirmation !== 'DELETE ALL') {
                alert('Deletion cancelled. You must type "DELETE ALL" exactly to proceed.');
                return false;
            }
            
            return confirm(`Final confirmation: Are you sure you want to delete ALL ${studentCount} students?\n\nThis will delete everything permanently!`);
        }
    </script>
</x-app-layout>


