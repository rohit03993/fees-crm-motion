<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\StoreStudentMiscChargeRequest;
use App\Http\Requests\StoreStudentPenaltyRequest;
use App\Http\Requests\UpdateStudentBasicInfoRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Course;
use App\Models\MiscCharge;
use App\Models\Penalty;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function __construct(private StudentService $studentService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Student::class);
        
        $query = Student::with(['course', 'branch', 'fee']);
        
        // Staff can only see students they created
        $user = auth()->user();
        if ($user && $user->isStaff() && !$user->isAdmin()) {
            $query->where('created_by', $user->id);
        }
        
        $students = $query->latest()->paginate(10);

        return view('students.index', compact('students'));
    }

    public function create(): View
    {
        $this->authorize('create', Student::class);
        
        $courses = Course::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        
        // Get guardian relations from settings
        $guardianRelations = \App\Models\Setting::getValue('student.guardian_relations', [
            'Father',
            'Mother',
            'Grandfather',
            'Grandmother',
            'Cousin Sister',
            'Cousin Brother',
            'Uncle',
            'Aunt',
            'Brother',
            'Sister',
            'Guardian',
            'Other'
        ]);

        return view('students.create', [
            'courses' => $courses,
            'branches' => $branches,
            'guardianRelations' => $guardianRelations,
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $this->authorize('create', Student::class);
        
        $student = $this->studentService->createStudent($request->validated());

        return redirect()->route('students.show', $student)->with('success', 'Student enrolled successfully.');
    }

    public function show(Student $student): View
    {
        $this->authorize('view', $student);
        
        // Refresh relationships to ensure we have the latest data
        $student->refresh();
        
        $student->load([
            'course',
            'branch',
            'fee.installments' => fn ($query) => $query->orderBy('installment_number'),
            'miscCharges' => fn ($query) => $query->orderBy('due_date'),
            'payments' => fn ($query) => $query->with(['installment', 'miscCharge', 'bank'])->orderByDesc('payment_date'),
            'penalties' => fn ($query) => $query->with('installment')->orderByDesc('applied_date'),
            'reminders' => fn ($query) => $query->with('installment')->orderByDesc('scheduled_for'),
            'reschedules' => fn ($query) => $query->with(['installment', 'requester', 'approver'])->orderByDesc('created_at'),
            'discounts' => fn ($query) => $query->with(['requester', 'approver'])->orderByDesc('created_at'),
        ]);
        
        // Refresh fee to get latest credit balance
        if ($student->fee) {
            $student->fee->refresh();
        }
        
        // Refresh relationships to ensure fresh data after any backend changes
        $student->loadMissing(['miscCharges', 'payments', 'penalties']);

        $totalProgramFee = optional($student->fee)->total_fee ?? 0;
        $installmentsTotal = optional($student->fee)->installments->sum('amount') ?? 0;
        $miscTotal = $student->miscCharges->sum('amount');
        $banks = Bank::active()->orderBy('name')->get();

        return view('students.show', [
            'student' => $student,
            'totalProgramFee' => $totalProgramFee,
            'installmentsTotal' => $installmentsTotal,
            'miscTotal' => $miscTotal,
            'banks' => $banks,
        ]);
    }

    public function updateBasicInfo(UpdateStudentBasicInfoRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        
        $validated = $request->validated();
        
        // Format WhatsApp number with +91
        $whatsapp = $validated['guardian_1_whatsapp'];
        if ($whatsapp && !str_starts_with($whatsapp, '+91')) {
            $whatsapp = '+91' . $whatsapp;
        }
        
        $student->update([
            'name' => $validated['name'],
            'guardian_1_name' => $validated['guardian_1_name'],
            'guardian_1_whatsapp' => $whatsapp,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('students.show', $student)
            ->with('success', 'Student basic information updated successfully.');
    }

    public function storeMiscCharge(StoreStudentMiscChargeRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('view', $student);
        
        $validated = $request->validated();
        
        $miscCharge = DB::transaction(function () use ($student, $validated) {
            return MiscCharge::create([
                'student_id' => $student->id,
                'course_id' => $student->course_id,
                'label' => $validated['label'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('students.show', $student)
            ->with('success', 'Miscellaneous charge added successfully. You can now record payment for it.');
    }

    public function storePenalty(StoreStudentPenaltyRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('view', $student);
        
        $validated = $request->validated();
        
        // If installment_id is provided, verify it belongs to this student
        if (!empty($validated['installment_id'])) {
            $installment = \App\Models\Installment::with('fee')->findOrFail($validated['installment_id']);
            if (!$installment->fee || $installment->fee->student_id !== $student->id) {
                return redirect()->route('students.show', $student)
                    ->with('error', 'Invalid installment selected.');
            }
        }
        
        $penalty = DB::transaction(function () use ($student, $validated) {
            return Penalty::create([
                'student_id' => $student->id,
                'installment_id' => null, // Manual penalties are not tied to installments
                'penalty_type' => 'manual', // Always store as 'manual' for user-created penalties
                'days_delayed' => 0, // Not applicable for manual penalties
                'penalty_rate' => 0, // Not applicable for manual penalties
                'penalty_amount' => $validated['penalty_amount'],
                'applied_date' => now()->toDateString(), // Automatically set to current date
                'status' => 'recorded',
                'remarks' => $validated['penalty_type_name'], // Store penalty type name in remarks
            ]);
        });

        return redirect()->route('students.show', $student)
            ->with('success', 'Penalty added successfully. You can now record payment for it.');
    }
}
