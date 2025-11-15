@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Discount Requests</h2>
                <p class="mt-1 text-sm text-gray-500">Review and act on staff-submitted discount requests.</p>
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
                    <span class="text-sm text-gray-500">Total requests: {{ $discounts->total() }}</span>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Amount</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Reason</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($discounts as $discount)
                                <tr class="bg-white">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $discount->student->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $discount->student->student_uid }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        ₹{{ number_format($discount->amount, 2) }}
                                        <div class="text-xs text-gray-400">Requested {{ $discount->created_at->format('d M Y H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <p class="line-clamp-3">{{ $discount->reason }}</p>
                                        @if($discount->document_path)
                                            <a href="{{ Storage::disk('public')->url($discount->document_path) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-500 mt-1 inline-block">View document</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @class([
                                                'bg-indigo-50 text-indigo-700' => $discount->status === 'pending',
                                                'bg-emerald-50 text-emerald-700' => $discount->status === 'approved',
                                                'bg-red-50 text-red-700' => $discount->status === 'rejected',
                                            ])">
                                            {{ ucfirst($discount->status) }}
                                        </span>
                                        @if($discount->decision_notes)
                                            <div class="mt-1 text-xs text-gray-500">Notes: {{ $discount->decision_notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($discount->status === 'pending')
                                            <form method="POST" action="{{ route('discounts.update', $discount) }}" class="space-y-2">
                                                @csrf
                                                @method('PUT')
                                                <textarea name="decision_notes" rows="2" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-xs" placeholder="Decision notes (optional)"></textarea>
                                                <div class="flex items-center gap-2">
                                                    <button name="decision" value="approved" class="px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">Approve</button>
                                                    <button name="decision" value="rejected" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700">Reject</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-xs text-gray-500 space-y-1">
                                                <div>Updated {{ $discount->approved_at?->format('d M Y H:i') ?? '—' }}</div>
                                                <div>By {{ $discount->approver?->name ?? '—' }}</div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No discount requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-6">
                        {{ $discounts->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


