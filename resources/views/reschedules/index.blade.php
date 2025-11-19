<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reschedule Requests</h2>
                <p class="mt-1 text-sm text-gray-500">Review and decide on installment reschedule requests submitted by staff.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Total requests: {{ $reschedules->total() }}</span>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Installment</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Requested</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Reason</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($reschedules as $reschedule)
                                <tr class="bg-white">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $reschedule->student->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $reschedule->student->student_uid }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        #{{ $reschedule->installment->installment_number }} &middot;
                                        <span class="text-xs text-gray-500">Current: {{ $reschedule->old_due_date->format('d M Y') }}</span>
                                        <div class="text-xs text-indigo-600">Proposed: {{ $reschedule->new_due_date->format('d M Y') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <div>{{ $reschedule->created_at->format('d M Y H:i') }}</div>
                                        <div class="text-xs text-gray-500">Attempt {{ $reschedule->attempt_number }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <p class="line-clamp-3">{{ $reschedule->reason }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @class([
                                                'bg-indigo-50 text-indigo-700' => $reschedule->status === 'pending',
                                                'bg-emerald-50 text-emerald-700' => $reschedule->status === 'approved',
                                                'bg-red-50 text-red-700' => $reschedule->status === 'rejected',
                                            ])">
                                            {{ ucfirst($reschedule->status) }}
                                        </span>
                                        @if($reschedule->decision_notes)
                                            <div class="mt-1 text-xs text-gray-500">Notes: {{ $reschedule->decision_notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($reschedule->status === 'pending')
                                            @can('approve-reschedules')
                                                <form method="POST" action="{{ route('reschedules.update', $reschedule) }}" class="space-y-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <textarea name="decision_notes" rows="2" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs" placeholder="Add decision notes (optional)"></textarea>
                                                    <div class="flex items-center gap-2">
                                                        <button name="decision" value="approved" class="px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">Approve</button>
                                                        <button name="decision" value="rejected" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700">Reject</button>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="text-xs text-gray-500 italic">No permission to approve</div>
                                            @endcan
                                        @else
                                            <div class="text-xs text-gray-500 space-y-1">
                                                <div>Updated {{ $reschedule->approved_at?->format('d M Y H:i') ?? '—' }}</div>
                                                <div>By {{ $reschedule->approver?->name ?? '—' }}</div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No reschedule requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-6">
                        {{ $reschedules->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


