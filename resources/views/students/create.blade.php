@php
    $today = now()->toDateString();
    $maxInstallmentRows = 6;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-2xl text-gray-900">Enroll New Student</h2>
            <p class="text-sm text-gray-500">Capture admission details and the agreed fee plan.</p>
        </div>
    </x-slot>

    <div class="py-10 bg-slate-50/60">
        <div class="max-w-6xl mx-auto lg:px-8 sm:px-6 px-4">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full px-3 py-1 shadow-sm">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white text-xs font-semibold">1</span>
                        Student details
                    </div>
                    <span class="hidden sm:block">→</span>
                    <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full px-3 py-1 shadow-sm">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white text-xs font-semibold">2</span>
                        Fee plan & instalments
                    </div>
                </div>
                <div class="text-xs text-gray-400">All fields marked with * are required.</div>
            </div>

            <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <header>
                        <h3 class="text-lg font-semibold text-gray-900">Student Information</h3>
                        <p class="text-sm text-gray-500">Contact details and course allocation.</p>
                    </header>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Student Name with Photo -->
                        <div class="md:col-span-2">
                            <div class="flex gap-4 items-start">
                                <div class="flex-1">
                                    <x-input-label for="name" value="Student Name *" />
                                    <x-text-input id="name" name="name" type="text" value="{{ old('name') }}" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div class="w-32">
                                    <x-input-label for="student_photo" value="Photo" />
                                    <div class="mt-1">
                                        <input type="file" id="student_photo" name="student_photo" accept="image/jpeg,image/jpg,image/png" 
                                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                        <div id="student_photo_preview" class="mt-2 hidden">
                                            <img id="student_photo_preview_img" src="" alt="Student photo preview" class="w-full h-32 object-cover rounded-lg border border-gray-200" />
                                            <button type="button" onclick="clearPhotoPreview('student_photo')" class="mt-1 text-xs text-red-600 hover:text-red-700">Remove</button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Max 1 MB</p>
                                    <x-input-error :messages="$errors->get('student_photo')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Guardian 1 - Compressed Layout -->
                        <div class="md:col-span-2 border-t border-gray-200 pt-4 mt-2">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Guardian 1</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <x-input-label for="guardian_1_name" value="Name *" />
                                    <x-text-input id="guardian_1_name" name="guardian_1_name" type="text" value="{{ old('guardian_1_name') }}" class="mt-1 block w-full text-sm" required />
                                    <x-input-error :messages="$errors->get('guardian_1_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_1_whatsapp" value="WhatsApp *" />
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-2 flex items-center text-xs font-medium text-gray-700">+91</span>
                                        <input id="guardian_1_whatsapp" name="guardian_1_whatsapp" type="tel" pattern="[0-9]{10}" maxlength="10" 
                                               value="{{ old('guardian_1_whatsapp') ? preg_replace('/^\+91|[^0-9]/', '', old('guardian_1_whatsapp')) : '' }}" 
                                               class="block w-full text-sm rounded-xl border-gray-300 pl-12 pr-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('guardian_1_whatsapp') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" 
                                               placeholder="10 digits" required />
                                    </div>
                                    <x-input-error :messages="$errors->get('guardian_1_whatsapp')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_1_relation" value="Relation *" />
                                    <select id="guardian_1_relation" name="guardian_1_relation" class="mt-1 block w-full text-sm rounded-xl border-gray-300 py-1.5 focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">Select</option>
                                        @foreach($guardianRelations as $relation)
                                            <option value="{{ $relation }}" @selected(old('guardian_1_relation') == $relation)>{{ $relation }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('guardian_1_relation')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_1_photo" value="Passport Photo" />
                                    <div class="mt-1">
                                        <input type="file" id="guardian_1_photo" name="guardian_1_photo" accept="image/jpeg,image/jpg,image/png" 
                                               class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                        <div id="guardian_1_photo_preview" class="mt-2 hidden">
                                            <img id="guardian_1_photo_preview_img" src="" alt="Guardian 1 photo preview" class="w-full h-20 object-cover rounded-lg border border-gray-200" />
                                            <button type="button" onclick="clearPhotoPreview('guardian_1_photo')" class="mt-1 text-xs text-red-600 hover:text-red-700">Remove</button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Max 1 MB</p>
                                    <x-input-error :messages="$errors->get('guardian_1_photo')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Guardian 2 - Compressed Layout -->
                        <div class="md:col-span-2 border-t border-gray-200 pt-4 mt-2">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Guardian 2 (Optional)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <x-input-label for="guardian_2_name" value="Name" />
                                    <x-text-input id="guardian_2_name" name="guardian_2_name" type="text" value="{{ old('guardian_2_name') }}" class="mt-1 block w-full text-sm" />
                                    <x-input-error :messages="$errors->get('guardian_2_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_2_whatsapp" value="WhatsApp" />
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-2 flex items-center text-xs font-medium text-gray-700">+91</span>
                                        <input id="guardian_2_whatsapp" name="guardian_2_whatsapp" type="tel" pattern="[0-9]{10}" maxlength="10" 
                                               value="{{ old('guardian_2_whatsapp') ? preg_replace('/^\+91|[^0-9]/', '', old('guardian_2_whatsapp')) : '' }}" 
                                               class="block w-full text-sm rounded-xl border-gray-300 pl-12 pr-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('guardian_2_whatsapp') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" 
                                               placeholder="10 digits" />
                                    </div>
                                    <x-input-error :messages="$errors->get('guardian_2_whatsapp')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_2_relation" value="Relation" />
                                    <select id="guardian_2_relation" name="guardian_2_relation" class="mt-1 block w-full text-sm rounded-xl border-gray-300 py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select</option>
                                        @foreach($guardianRelations as $relation)
                                            <option value="{{ $relation }}" @selected(old('guardian_2_relation') == $relation)>{{ $relation }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('guardian_2_relation')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guardian_2_photo" value="Passport Photo" />
                                    <div class="mt-1">
                                        <input type="file" id="guardian_2_photo" name="guardian_2_photo" accept="image/jpeg,image/jpg,image/png" 
                                               class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                        <div id="guardian_2_photo_preview" class="mt-2 hidden">
                                            <img id="guardian_2_photo_preview_img" src="" alt="Guardian 2 photo preview" class="w-full h-20 object-cover rounded-lg border border-gray-200" />
                                            <button type="button" onclick="clearPhotoPreview('guardian_2_photo')" class="mt-1 text-xs text-red-600 hover:text-red-700">Remove</button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Max 1 MB</p>
                                    <x-input-error :messages="$errors->get('guardian_2_photo')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-2 lg:col-span-1">
                            <x-input-label for="course_id" value="Course *" />
                            <select id="course_id" name="course_id" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select course</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('course_id')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2 lg:col-span-1">
                            <x-input-label for="branch_id" value="Branch *" />
                            <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2 lg:col-span-1">
                            <x-input-label for="admission_date" value="Admission Date *" />
                            <x-text-input id="admission_date" name="admission_date" type="date" value="{{ old('admission_date', $today) }}" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('admission_date')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Miscellaneous Charges</h3>
                            <p class="text-sm text-gray-500">Add any additional charges (e.g., books, uniforms, materials). These will be saved and shown in the student's profile.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="add-misc-charge-row" class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-100">
                                + Add Miscellaneous Charge
                            </button>
                        </div>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">#</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Select or Enter Name</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody id="misc-charge-rows" class="divide-y divide-gray-100">
                            </tbody>
                        </table>
                    </div>
                    <x-input-error :messages="$errors->get('misc_charges')" class="mt-2" />
                </section>

                <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-6">
                    <header class="flex flex-col gap-1">
                        <h3 class="text-lg font-semibold text-gray-900">Programme Fee &amp; Channel Split</h3>
                        <p class="text-sm text-gray-500">Enter the total fee along with the cash vs online plan agreed with the parent.</p>
                    </header>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="total_fee" value="Total Programme Fee (₹) *" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">₹</span>
                                <input id="total_fee" name="total_fee" type="number" min="0" step="0.01" value="{{ old('total_fee') }}"
                                       class="block w-full rounded-2xl border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <x-input-error :messages="$errors->get('total_fee')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="cash_allowance" value="Planned Cash Collection (₹) *" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">₹</span>
                                <input id="cash_allowance" name="cash_allowance" type="number" min="0" step="0.01" value="{{ old('cash_allowance') }}"
                                       class="block w-full rounded-2xl border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <x-input-error :messages="$errors->get('cash_allowance')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="online_allowance" value="Planned Online Collection (₹) *" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">₹</span>
                                <input id="online_allowance" name="online_allowance" type="number" min="0" step="0.01" value="{{ old('online_allowance') }}"
                                       class="block w-full rounded-2xl border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <x-input-error :messages="$errors->get('online_allowance')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <header>
                        <h3 class="text-lg font-semibold text-gray-900">Payment Plan</h3>
                        <p class="text-sm text-gray-500">Choose one-time payment or build an instalment schedule.</p>
                    </header>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <span class="block text-sm font-semibold text-gray-700">Payment Mode *</span>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <label class="flex items-start gap-3 rounded-2xl border px-4 py-3 cursor-pointer transition payment-option" data-mode="full">
                                    <input type="radio" name="payment_mode" value="full" class="mt-1" {{ old('payment_mode', 'installments') === 'full' ? 'checked' : '' }}>
                                    <div>
                                        <p class="text-sm font-semibold">One-time payment</p>
                                        <p class="text-xs text-gray-500">Collect the entire fee in a single payment.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 rounded-2xl border px-4 py-3 cursor-pointer transition payment-option" data-mode="installments">
                                    <input type="radio" name="payment_mode" value="installments" class="mt-1" {{ old('payment_mode', 'installments') === 'installments' ? 'checked' : '' }}>
                                    <div>
                                        <p class="text-sm font-semibold">Instalments</p>
                                        <p class="text-xs text-gray-500">Split the programme fee across planned instalments.</p>
                                    </div>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('payment_mode')" class="mt-2" />
                        </div>

                    </div>
                </section>

                <section id="instalment-schedule-section" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-4 {{ old('payment_mode', 'installments') === 'installments' ? '' : 'hidden' }}">
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Instalment Schedule</h3>
                            <p class="text-sm text-gray-500">Add instalments manually. Adjust dates or amounts as needed.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="add-installment-row" class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-100">
                                + Add instalment
                            </button>
                        </div>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">#</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody id="installment-rows" class="divide-y divide-gray-100">
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Remaining Amount Summary -->
                    <div id="remaining-summary" class="mt-4 rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4 hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-indigo-900">Remaining Amount:</p>
                                <p class="mt-1 text-2xl font-bold text-indigo-600">₹<span id="remaining-amount-display">0.00</span></p>
                            </div>
                            <div class="text-sm text-indigo-700">
                                <p>Total Programme Fee: ₹<span id="total-fee-display">0.00</span></p>
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('installments')" class="mt-2" />
                </section>

                <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="text-sm text-gray-600 space-y-1">
                        <div>Totals are validated when you save the student. The system enforces that cash + online = programme fee and that instalments add up correctly.</div>
                        <div class="text-xs text-gray-500">Fees and instalments can be adjusted later from the student profile if needed.</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('students.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Save Student</x-primary-button>
                    </div>
                </section>
            </form>

            <section class="mt-10 bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                <h4 class="text-base font-semibold text-gray-900 mb-2">Tips</h4>
                <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                    <li>Split the total fee between cash and online exactly as written on the admission note. The system tracks the online allowance and raises GST penalties automatically if exceeded.</li>
                    <li>For instalments, fill as many rows as required. Leave unused rows blank—they will be ignored.</li>
                    <li>You can edit or add instalments later from the student profile if plans change.</li>
                </ul>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Phone number formatting - only allow 10 digits for guardian WhatsApp numbers
            const guardian1WhatsappInput = document.getElementById('guardian_1_whatsapp');
            const guardian2WhatsappInput = document.getElementById('guardian_2_whatsapp');

            function formatPhoneInput(input) {
                if (!input) return;
                
                // Only allow digits
                input.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
                });

                // Prevent paste of non-numeric values
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = paste.replace(/[^0-9]/g, '').substring(0, 10);
                    this.value = digits;
                });
            }

            if (guardian1WhatsappInput) {
                formatPhoneInput(guardian1WhatsappInput);
            }

            if (guardian2WhatsappInput) {
                formatPhoneInput(guardian2WhatsappInput);
            }

            // Photo preview functionality
            function setupPhotoPreview(inputId, previewId, previewImgId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const previewImg = document.getElementById(previewImgId);
                
                if (input && preview && previewImg) {
                    input.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            // Check file size (1 MB = 1024 * 1024 bytes)
                            if (file.size > 1024 * 1024) {
                                alert('File size must be less than 1 MB. Please select a smaller image.');
                                input.value = '';
                                preview.classList.add('hidden');
                                return;
                            }
                            
                            // Check file type
                            if (!file.type.match('image/jpeg') && !file.type.match('image/jpg') && !file.type.match('image/png')) {
                                alert('Please select a valid image file (JPEG, JPG, or PNG).');
                                input.value = '';
                                preview.classList.add('hidden');
                                return;
                            }
                            
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                preview.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }

            // Setup photo previews
            setupPhotoPreview('student_photo', 'student_photo_preview', 'student_photo_preview_img');
            setupPhotoPreview('guardian_1_photo', 'guardian_1_photo_preview', 'guardian_1_photo_preview_img');
            setupPhotoPreview('guardian_2_photo', 'guardian_2_photo_preview', 'guardian_2_photo_preview_img');

            // Clear photo preview function (used by remove buttons)
            window.clearPhotoPreview = function(inputId) {
                const input = document.getElementById(inputId);
                const previewId = inputId + '_preview';
                const preview = document.getElementById(previewId);
                
                if (input) {
                    input.value = '';
                }
                if (preview) {
                    preview.classList.add('hidden');
                }
            };

            // Auto-calculate Planned Online Collection and Planned Cash Collection
            const totalFeeInput = document.getElementById('total_fee');
            const cashAllowanceInput = document.getElementById('cash_allowance');
            const onlineAllowanceInput = document.getElementById('online_allowance');
            
            // Track which field was last modified
            let lastModifiedField = null;

            function calculateSplit(modifiedField) {
                const totalFee = parseFloat(totalFeeInput?.value || 0);
                const cashAllowance = parseFloat(cashAllowanceInput?.value || 0);
                const onlineAllowance = parseFloat(onlineAllowanceInput?.value || 0);

                // If total fee is not entered, don't do anything
                if (!totalFee || totalFee <= 0) {
                    return;
                }

                // Determine which field to calculate based on the modified field
                if (modifiedField === 'cash' && cashAllowance >= 0) {
                    // Cash was modified - calculate online
                    const calculatedOnline = totalFee - cashAllowance;
                    if (calculatedOnline >= 0 && calculatedOnline <= totalFee) {
                        onlineAllowanceInput.value = calculatedOnline.toFixed(2);
                    }
                } else if (modifiedField === 'online' && onlineAllowance >= 0) {
                    // Online was modified - calculate cash
                    const calculatedCash = totalFee - onlineAllowance;
                    if (calculatedCash >= 0 && calculatedCash <= totalFee) {
                        cashAllowanceInput.value = calculatedCash.toFixed(2);
                    }
                }
            }

            // Add event listeners for auto-calculation
            if (totalFeeInput) {
                totalFeeInput.addEventListener('input', function() {
                    const totalFee = parseFloat(this.value || 0);
                    if (totalFee > 0) {
                        // If total fee is changed and one of the allowances is already filled, recalculate
                        const cashAllowance = parseFloat(cashAllowanceInput?.value || 0);
                        const onlineAllowance = parseFloat(onlineAllowanceInput?.value || 0);
                        
                        if (cashAllowance > 0 && !onlineAllowanceInput.value) {
                            calculateSplit('cash');
                        } else if (onlineAllowance > 0 && !cashAllowanceInput.value) {
                            calculateSplit('online');
                        }
                        
                        // If instalments mode is selected and no installment exists yet, create first one
                        const selectedMode = document.querySelector('input[name="payment_mode"]:checked')?.value;
                        if (selectedMode === 'installments' && scheduleSection && !scheduleSection.classList.contains('hidden')) {
                            if (!rowsContainer.querySelector('.installment-row') && !rowsContainer.querySelector('.empty-row')) {
                                createRow({ amount: totalFee.toFixed(2) }, false);
                            }
                        }
                    }
                    // Update remaining amount when total fee changes
                    updateRemainingAmount();
                });
                
                // Also update when total fee changes via blur
                totalFeeInput.addEventListener('change', function() {
                    updateRemainingAmount();
                });
            }

            if (cashAllowanceInput) {
                cashAllowanceInput.addEventListener('input', function() {
                    const totalFee = parseFloat(totalFeeInput?.value || 0);
                    const cashAllowance = parseFloat(this.value || 0);
                    
                    // Prevent entering value greater than total fee
                    if (totalFee > 0 && cashAllowance > totalFee) {
                        this.value = totalFee.toFixed(2);
                        if (onlineAllowanceInput) {
                            onlineAllowanceInput.value = '0.00';
                        }
                        return;
                    }
                    
                    lastModifiedField = 'cash';
                    calculateSplit('cash');
                });
                
                cashAllowanceInput.addEventListener('blur', function() {
                    if (lastModifiedField === 'cash') {
                        calculateSplit('cash');
                    }
                });
            }

            if (onlineAllowanceInput) {
                onlineAllowanceInput.addEventListener('input', function() {
                    const totalFee = parseFloat(totalFeeInput?.value || 0);
                    const onlineAllowance = parseFloat(this.value || 0);
                    
                    // Prevent entering value greater than total fee
                    if (totalFee > 0 && onlineAllowance > totalFee) {
                        this.value = totalFee.toFixed(2);
                        if (cashAllowanceInput) {
                            cashAllowanceInput.value = '0.00';
                        }
                        return;
                    }
                    
                    lastModifiedField = 'online';
                    calculateSplit('online');
                });
                
                onlineAllowanceInput.addEventListener('blur', function() {
                    if (lastModifiedField === 'online') {
                        calculateSplit('online');
                    }
                });
            }

            const paymentOptions = document.querySelectorAll('.payment-option input[type="radio"]');
            const paymentOptionCards = document.querySelectorAll('.payment-option');
            const scheduleSection = document.getElementById('instalment-schedule-section');
            const addRowButton = document.getElementById('add-installment-row');
            const rowsContainer = document.getElementById('installment-rows');
            const oldInstallmentsRaw = @json(old('installments', []));
            const defaultMode = '{{ old('payment_mode', 'installments') }}';

            const oldInstallments = Array.isArray(oldInstallmentsRaw)
                ? oldInstallmentsRaw.filter(item => item && (item.due_date || item.amount))
                : [];

            const styleSelectedOption = () => {
                paymentOptionCards.forEach(card => card.classList.remove('border-indigo-500', 'bg-indigo-50', 'shadow-sm'));
                paymentOptions.forEach(radio => {
                    if (radio.checked) {
                        radio.closest('.payment-option').classList.add('border-indigo-500', 'bg-indigo-50', 'shadow-sm');
                    }
                });
            };

            const removeEmptyState = () => {
                const placeholder = rowsContainer.querySelector('.empty-row');
                if (placeholder) {
                    placeholder.remove();
                }
            };

            const showEmptyState = () => {
                if (!rowsContainer.querySelector('.empty-row')) {
                const empty = document.createElement('tr');
                empty.className = 'bg-white empty-row';
                empty.innerHTML = `
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No instalments yet. Add instalments manually using the button above.</td>
                `;
                rowsContainer.appendChild(empty);
                }
            };

            // Function to get minimum date for an installment based on previous installments
            const getMinDateForInstallment = (currentRowIndex) => {
                const today = '{{ $today }}';
                const rows = Array.from(rowsContainer.querySelectorAll('.installment-row'));
                
                // If it's the first installment, min is today
                if (currentRowIndex === 0) {
                    return today;
                }
                
                // Find the latest due date from previous installments
                let latestPreviousDate = today;
                for (let i = 0; i < currentRowIndex; i++) {
                    const prevRow = rows[i];
                    if (prevRow) {
                        const prevDateInput = prevRow.querySelector('input[data-field="due_date"]');
                        if (prevDateInput && prevDateInput.value) {
                            const prevDateValue = prevDateInput.value;
                            // If this previous date is later than our current latest, update it
                            if (prevDateValue > latestPreviousDate) {
                                latestPreviousDate = prevDateValue;
                            }
                        }
                    }
                }
                
                // Return the day after the latest previous installment, or today, whichever is later
                if (latestPreviousDate > today) {
                    const prevDate = new Date(latestPreviousDate + 'T00:00:00');
                    prevDate.setDate(prevDate.getDate() + 1);
                    const nextDay = prevDate.toISOString().split('T')[0];
                    return nextDay > today ? nextDay : today;
                }
                
                return today;
            };
            
            // Function to update min dates for all installments
            const updateInstallmentMinDates = () => {
                const rows = Array.from(rowsContainer.querySelectorAll('.installment-row'));
                const today = '{{ $today }}';
                
                rows.forEach((row, index) => {
                    const dueDateInput = row.querySelector('input[data-field="due_date"]');
                    if (dueDateInput) {
                        const minDate = getMinDateForInstallment(index);
                        const oldMin = dueDateInput.getAttribute('min');
                        dueDateInput.setAttribute('min', minDate);
                        
                        // If current value is before min date, clear it and show alert
                        if (dueDateInput.value && dueDateInput.value < minDate) {
                            dueDateInput.value = '';
                            // Only show alert if user is actively changing, not on initial load
                            if (oldMin !== null && oldMin !== minDate) {
                                alert(`Installment ${index + 1} due date must be after ${minDate}. Please select a valid date.`);
                            }
                        }
                    }
                });
            };

            const reindexRows = () => {
                const rows = rowsContainer.querySelectorAll('.installment-row');
                rows.forEach((row, index) => {
                    row.dataset.index = index;
                    row.querySelector('.installment-index').textContent = index + 1;
                    row.querySelector('input[data-field="due_date"]').name = `installments[${index}][due_date]`;
                    row.querySelector('input[data-field="amount"]').name = `installments[${index}][amount]`;
                });

                // Update min dates after reindexing
                updateInstallmentMinDates();

                if (!rows.length) {
                    showEmptyState();
                }
            };

            const ensureScheduleVisible = () => {
                if (rowsContainer.children.length > 0) {
                    scheduleSection.classList.remove('hidden');
                }
            };

            const hideScheduleIfEmpty = () => {
                if (!rowsContainer.children.length) {
                    scheduleSection.classList.add('hidden');
                }
            };


                // Function to calculate and update remaining amount
                const updateRemainingAmount = () => {
                    const total = parseFloat(totalFeeInput?.value || 0);
                    if (!total || total <= 0) {
                        const remainingSummary = document.getElementById('remaining-summary');
                        if (remainingSummary) {
                            remainingSummary.classList.add('hidden');
                        }
                        return;
                    }
                    
                    const rows = Array.from(rowsContainer.querySelectorAll('.installment-row'));
                    let totalAllocated = 0;
                    
                    // Calculate total allocated
                    rows.forEach((row) => {
                        const amountInput = row.querySelector('input[data-field="amount"]');
                        const amount = parseFloat(amountInput?.value || 0);
                        totalAllocated += amount;
                    });
                    
                    // Update summary
                    const remainingAmount = total - totalAllocated;
                    const remainingSummary = document.getElementById('remaining-summary');
                    const remainingDisplay = document.getElementById('remaining-amount-display');
                    const totalFeeDisplay = document.getElementById('total-fee-display');
                    
                    if (remainingSummary && remainingDisplay && totalFeeDisplay) {
                        const remainingToShow = Math.max(0, remainingAmount); // Don't show negative in summary
                        remainingDisplay.textContent = remainingToShow.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        totalFeeDisplay.textContent = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        
                        // Show/hide summary
                        if (rows.length > 0) {
                            remainingSummary.classList.remove('hidden');
                            
                            // Color code based on remaining
                            if (remainingAmount < 0) {
                                remainingSummary.classList.remove('border-indigo-200', 'bg-indigo-50', 'border-emerald-200', 'bg-emerald-50');
                                remainingSummary.classList.add('border-red-200', 'bg-red-50');
                                remainingDisplay.classList.remove('text-indigo-600', 'text-emerald-600');
                                remainingDisplay.classList.add('text-red-600');
                            } else if (remainingAmount === 0 || Math.abs(remainingAmount) < 0.01) {
                                remainingSummary.classList.remove('border-indigo-200', 'bg-indigo-50', 'border-red-200', 'bg-red-50');
                                remainingSummary.classList.add('border-emerald-200', 'bg-emerald-50');
                                remainingDisplay.classList.remove('text-indigo-600', 'text-red-600');
                                remainingDisplay.classList.add('text-emerald-600');
                            } else {
                                remainingSummary.classList.remove('border-red-200', 'bg-red-50', 'border-emerald-200', 'bg-emerald-50');
                                remainingSummary.classList.add('border-indigo-200', 'bg-indigo-50');
                                remainingDisplay.classList.remove('text-red-600', 'text-emerald-600');
                                remainingDisplay.classList.add('text-indigo-600');
                            }
                        } else {
                            remainingSummary.classList.add('hidden');
                        }
                    }
                };

                const createRow = (data = {}) => {
                removeEmptyState();
                const row = document.createElement('tr');
                row.className = 'bg-white installment-row';
                
                // Use provided amount or empty
                const amountToFill = data.amount ?? '';
                
                // Get the current row index before adding
                const currentIndex = rowsContainer.querySelectorAll('.installment-row').length;
                const minDate = getMinDateForInstallment(currentIndex);
                
                row.innerHTML = `
                    <td class="px-4 py-2 font-semibold text-gray-700 installment-index align-top"></td>
                    <td class="px-4 py-2 align-top">
                        <input type="date" data-field="due_date" value="${data.due_date ?? ''}" min="${minDate}" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3">
                    </td>
                    <td class="px-4 py-2 align-top">
                        <input type="number" min="0" step="0.01" data-field="amount" value="${amountToFill}" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3">
                    </td>
                    <td class="px-4 py-2 text-right align-top">
                        <button type="button" class="text-xs font-semibold text-red-500 remove-installment">Remove</button>
                    </td>
                `;
                
                // Add event listener for amount changes
                const amountInput = row.querySelector('input[data-field="amount"]');
                const dueDateInput = row.querySelector('input[data-field="due_date"]');
                
                if (amountInput) {
                    amountInput.addEventListener('input', function() {
                        updateRemainingAmount();
                    });
                    
                    // Also trigger on blur to ensure final calculation
                    amountInput.addEventListener('blur', function() {
                        updateRemainingAmount();
                    });
                }
                
                // Update remaining and min dates when date is selected
                if (dueDateInput) {
                    dueDateInput.addEventListener('change', function() {
                        updateRemainingAmount();
                        // Update min dates for subsequent installments
                        updateInstallmentMinDates();
                    });
                }
                
                row.querySelector('.remove-installment').addEventListener('click', () => {
                    row.remove();
                    reindexRows();
                    updateRemainingAmount();
                    updateInstallmentMinDates(); // Update min dates after removal
                    hideScheduleIfEmpty();
                });
                
                rowsContainer.appendChild(row);
                reindexRows();
                updateInstallmentMinDates(); // Set min dates after adding
                updateRemainingAmount(); // Calculate remaining after adding row
            };

            const setMode = (mode) => {
                if (mode === 'installments') {
                    scheduleSection.classList.remove('hidden');
                    
                    // If no installments exist yet, create the first one with total fee pre-filled
                    if (!rowsContainer.querySelector('.installment-row')) {
                        const total = parseFloat(totalFeeInput?.value || 0);
                        if (total > 0) {
                            // Create first installment with total fee amount
                            createRow({ amount: total.toFixed(2) }, false);
                        } else {
                            showEmptyState();
                        }
                    }
                } else {
                    rowsContainer.innerHTML = '';
                    showEmptyState();
                    scheduleSection.classList.add('hidden');
                }
                styleSelectedOption();
            };

            addRowButton.addEventListener('click', () => {
                createRow({}); // Don't auto-fill amount
            });

            paymentOptions.forEach(radio => {
                radio.addEventListener('change', (event) => {
                    setMode(event.target.value);
                });
            });

            if (oldInstallments.length) {
                oldInstallments.forEach(item => createRow({
                    due_date: item.due_date || '',
                    amount: item.amount || ''
                }, false));
                // Update remaining amount and min dates after loading old installments
                updateRemainingAmount();
                updateInstallmentMinDates();
            } else {
                // Initialize based on default mode
                if (defaultMode === 'installments') {
                    const total = parseFloat(totalFeeInput?.value || 0);
                    if (total > 0) {
                        // Don't show empty state, createRow will handle it
                        // We'll create it in setMode instead
                    } else {
                        showEmptyState();
                    }
                } else {
                    showEmptyState();
                }
            }

            setMode(defaultMode);

            // ========== MISCELLANEOUS CHARGES MANAGEMENT ==========
            const miscChargeRowsContainer = document.getElementById('misc-charge-rows');
            const addMiscChargeButton = document.getElementById('add-misc-charge-row');
            const courseSelect = document.getElementById('course_id');
            const oldMiscChargesRaw = @json(old('misc_charges', []));
            
            const oldMiscCharges = Array.isArray(oldMiscChargesRaw)
                ? oldMiscChargesRaw.filter(item => item && (item.label || item.amount))
                : [];

            // Store available misc charges
            let availableCharges = [];

            // Fetch available misc charges when course is selected
            const fetchAvailableCharges = async (courseId) => {
                try {
                    const response = await fetch(`{{ route('misc-charges.available') }}?course_id=${courseId || ''}`);
                    if (response.ok) {
                        availableCharges = await response.json();
                        // Update dropdowns in all existing rows
                        updateMiscChargeDropdowns();
                    }
                    return availableCharges;
                } catch (error) {
                    console.error('Error fetching misc charges:', error);
                    return [];
                }
            };

            // Update dropdowns in all misc charge rows
            const updateMiscChargeDropdowns = () => {
                const rows = miscChargeRowsContainer.querySelectorAll('.misc-charge-row');
                rows.forEach(row => {
                    const selectDropdown = row.querySelector('select[data-field="charge-select"]');
                    if (selectDropdown) {
                        const currentValue = selectDropdown.value;
                        selectDropdown.innerHTML = `
                            <option value="__custom__">-- Enter Custom Charge --</option>
                            ${availableCharges.map(charge => 
                                `<option value="${charge.id}" ${charge.id == currentValue ? 'selected' : ''}>
                                    ${charge.label} - ₹${charge.amount.toFixed(2)} ${charge.course_name ? '(' + charge.course_name + ')' : '(Global)'}
                                </option>`
                            ).join('')}
                        `;
                    }
                });
            };

            // Fetch charges when course changes
            if (courseSelect) {
                courseSelect.addEventListener('change', function() {
                    fetchAvailableCharges(this.value);
                });
                
                // Fetch initial charges (global charges even if no course selected)
                // If course is already selected, fetch course-specific + global
                // If no course, fetch only global charges
                const initialCourseId = courseSelect.value || '';
                fetchAvailableCharges(initialCourseId);
            } else {
                // If course select doesn't exist yet, fetch global charges
                fetchAvailableCharges('');
            }

            const removeMiscChargeEmptyState = () => {
                const placeholder = miscChargeRowsContainer.querySelector('.empty-misc-row');
                if (placeholder) {
                    placeholder.remove();
                }
            };

            const showMiscChargeEmptyState = () => {
                if (!miscChargeRowsContainer.querySelector('.empty-misc-row')) {
                    const empty = document.createElement('tr');
                    empty.className = 'bg-white empty-misc-row';
                    empty.innerHTML = `
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No miscellaneous charges yet. Add charges manually using the button above.</td>
                    `;
                    miscChargeRowsContainer.appendChild(empty);
                }
            };

            const reindexMiscChargeRows = () => {
                const rows = miscChargeRowsContainer.querySelectorAll('.misc-charge-row');
                const today = '{{ $today }}';
                
                rows.forEach((row, index) => {
                    row.dataset.index = index;
                    row.querySelector('.misc-charge-index').textContent = index + 1;
                    const labelInput = row.querySelector('input[data-field="label"]');
                    const amountInput = row.querySelector('input[data-field="amount"]');
                    const dueDateInput = row.querySelector('input[data-field="due_date"]');
                    
                    if (labelInput) labelInput.name = `misc_charges[${index}][label]`;
                    if (amountInput) amountInput.name = `misc_charges[${index}][amount]`;
                    if (dueDateInput) {
                        dueDateInput.name = `misc_charges[${index}][due_date]`;
                        // Ensure min date is set to today
                        dueDateInput.setAttribute('min', today);
                        // If current value is before today, clear it
                        if (dueDateInput.value && dueDateInput.value < today) {
                            dueDateInput.value = '';
                        }
                    }
                });

                if (!rows.length) {
                    showMiscChargeEmptyState();
                }
            };

            const createMiscChargeRow = (data = {}) => {
                removeMiscChargeEmptyState();
                
                // If charges haven't been fetched yet, fetch them first
                if (availableCharges.length === 0) {
                    const courseId = courseSelect ? (courseSelect.value || '') : '';
                    fetchAvailableCharges(courseId).then(() => {
                        // Create row after charges are fetched
                        createMiscChargeRowAfterFetch(data);
                    });
                    return;
                }
                
                createMiscChargeRowAfterFetch(data);
            };

            const createMiscChargeRowAfterFetch = (data = {}) => {
                const row = document.createElement('tr');
                row.className = 'bg-white misc-charge-row';
                
                // Build dropdown options - removed "Select Pre-defined Charge" option
                const dropdownOptions = `
                    <option value="__custom__" ${!data.predefinedChargeId && data.label ? 'selected' : ''}>-- Enter Custom Charge --</option>
                    ${availableCharges.map(charge => 
                        `<option value="${charge.id}" ${data.predefinedChargeId == charge.id ? 'selected' : ''}>
                            ${charge.label} - ₹${charge.amount.toFixed(2)} ${charge.course_name ? '(' + charge.course_name + ')' : '(Global)'}
                        </option>`
                    ).join('')}
                `;
                
                // Determine initial state - show dropdown if predefined charge is selected, otherwise show text input
                // Default to showing text input (custom charge) when no data is provided
                const hasPredefinedCharge = data.predefinedChargeId && data.predefinedChargeId !== '__custom__' && data.predefinedChargeId !== '';
                const showDropdown = hasPredefinedCharge;
                const showTextInput = !hasPredefinedCharge;
                
                row.innerHTML = `
                    <td class="px-4 py-2 font-semibold text-gray-700 misc-charge-index align-top"></td>
                    <td class="px-4 py-2 align-top">
                        <select data-field="charge-select" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3 ${showTextInput ? 'hidden' : ''}">
                            ${dropdownOptions}
                        </select>
                        <div class="relative ${showDropdown ? 'hidden' : ''}">
                            <input type="text" data-field="label" value="${data.label ?? ''}" placeholder="e.g., Books, Uniform, Materials" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3 pr-20">
                            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-indigo-600 hover:text-indigo-700 font-medium switch-to-dropdown" title="Select from predefined charges">Select</button>
                        </div>
                    </td>
                    <td class="px-4 py-2 align-top">
                        <input type="number" min="0" step="0.01" data-field="amount" value="${data.amount ?? ''}" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3">
                    </td>
                    <td class="px-4 py-2 align-top">
                        <input type="date" data-field="due_date" value="${data.due_date ?? ''}" min="{{ $today }}" class="block w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm py-2 px-3">
                    </td>
                    <td class="px-4 py-2 text-right align-top">
                        <button type="button" class="text-xs font-semibold text-red-500 remove-misc-charge">Remove</button>
                    </td>
                `;
                
                // Handle dropdown change
                const selectDropdown = row.querySelector('select[data-field="charge-select"]');
                const labelInput = row.querySelector('input[data-field="label"]');
                const labelInputWrapper = labelInput ? labelInput.closest('div.relative') : null;
                const switchButton = row.querySelector('.switch-to-dropdown');
                const amountInput = row.querySelector('input[data-field="amount"]');
                const dueDateInput = row.querySelector('input[data-field="due_date"]');
                
                // Function to switch to dropdown view
                const switchToDropdown = () => {
                    if (selectDropdown && labelInputWrapper) {
                        selectDropdown.classList.remove('hidden');
                        labelInputWrapper.classList.add('hidden');
                        // Clear the current selection so user can choose from dropdown
                        // Don't set to '__custom__' as it will trigger change event
                        if (availableCharges.length > 0) {
                            selectDropdown.value = availableCharges[0].id;
                            // Trigger the change manually to populate fields
                            selectDropdown.dispatchEvent(new Event('change'));
                        } else {
                            selectDropdown.value = '';
                        }
                    }
                };
                
                // Function to switch to text input view
                const switchToTextInput = () => {
                    if (selectDropdown && labelInputWrapper) {
                        selectDropdown.classList.add('hidden');
                        labelInputWrapper.classList.remove('hidden');
                        labelInput.value = '';
                        amountInput.value = '';
                        dueDateInput.value = '';
                    }
                };
                
                if (selectDropdown) {
                    selectDropdown.addEventListener('change', function() {
                        const today = '{{ $today }}';
                        
                        if (this.value === '__custom__') {
                            // Show text input, hide dropdown for custom entry
                            switchToTextInput();
                        } else if (this.value) {
                            // Show dropdown, hide text input and populate from selected charge
                            if (labelInputWrapper) labelInputWrapper.classList.add('hidden');
                            selectDropdown.classList.remove('hidden');
                            const selectedCharge = availableCharges.find(c => c.id == this.value);
                            if (selectedCharge) {
                                labelInput.value = selectedCharge.label;
                                amountInput.value = selectedCharge.amount;
                                if (selectedCharge.due_date && selectedCharge.due_date >= today) {
                                    dueDateInput.value = selectedCharge.due_date;
                                } else {
                                    dueDateInput.value = '';
                                }
                            }
                        }
                    });
                }
                
                // Handle switch button click
                if (switchButton) {
                    switchButton.addEventListener('click', switchToDropdown);
                }
                
                // Ensure min date is always set to today for misc charge due dates
                if (dueDateInput) {
                    const today = '{{ $today }}';
                    dueDateInput.setAttribute('min', today);
                    
                    // Validate on change
                    dueDateInput.addEventListener('change', function() {
                        if (this.value && this.value < today) {
                            alert('Due date cannot be in the past. Please select today or a future date.');
                            this.value = '';
                        }
                    });
                }
                
                row.querySelector('.remove-misc-charge').addEventListener('click', () => {
                    row.remove();
                    reindexMiscChargeRows();
                });
                
                miscChargeRowsContainer.appendChild(row);
                reindexMiscChargeRows();
            };

            if (addMiscChargeButton) {
                addMiscChargeButton.addEventListener('click', () => {
                    createMiscChargeRow({});
                });
            }

            // Load old misc charges if any
            if (oldMiscCharges.length) {
                oldMiscCharges.forEach(item => createMiscChargeRow({
                    label: item.label || '',
                    amount: item.amount || '',
                    due_date: item.due_date || ''
                }));
            } else {
                showMiscChargeEmptyState();
            }
        });
    </script>
</x-app-layout>

