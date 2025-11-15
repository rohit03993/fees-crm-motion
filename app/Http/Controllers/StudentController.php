<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StudentController extends Controller
{
    public function __construct(private StudentService $studentService)
    {
    }

    public function index(): View
    {
        $students = Student::with(['course', 'branch', 'fee'])
            ->latest()
            ->paginate(10);

        return view('students.index', compact('students'));
    }

    public function create(): View
    {
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
        $student = $this->studentService->createStudent($request->validated());

        return redirect()->route('students.show', $student)->with('success', 'Student enrolled successfully.');
    }

    public function show(Student $student): View
    {
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
}
