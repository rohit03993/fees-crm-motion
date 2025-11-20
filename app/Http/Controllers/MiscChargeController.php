<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\MiscCharge;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MiscChargeController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-misc-charges');
        
        // Get all course-level and global misc charges grouped by course
        $chargesByCourse = MiscCharge::whereNull('student_id')
            ->whereNotNull('course_id')
            ->with('course')
            ->orderBy('course_id')
            ->orderBy('due_date')
            ->get()
            ->groupBy('course_id');
        
        // Get global charges (no course_id)
        $globalCharges = MiscCharge::whereNull('student_id')
            ->whereNull('course_id')
            ->orderBy('due_date')
            ->get();

        $courses = Course::where('is_active', true)->orderBy('name')->get();

        return view('misc-charges.index', compact('chargesByCourse', 'globalCharges', 'courses'));
    }

    public function create(): View
    {
        $this->authorize('manage-misc-charges');
        
        $courses = Course::where('is_active', true)->orderBy('name')->get();

        return view('misc-charges.create', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-misc-charges');
        
        $validated = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'], // Allow null for global charges
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            // Create the template charge (course_id may be null for global, student_id always null)
            $charge = MiscCharge::create([
                'course_id' => $validated['course_id'] ?? null, // Can be null for global charges
                'student_id' => null, // Template charge (not student-specific)
                'label' => $validated['label'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // If course_id is set, automatically create this charge for all existing students in this course
            if ($validated['course_id']) {
                $students = Student::where('course_id', $validated['course_id'])
                    ->where('status', 'active')
                    ->get();

                foreach ($students as $student) {
                    // Check if this charge already exists for this student (avoid duplicates)
                    $exists = MiscCharge::where('student_id', $student->id)
                        ->where('course_id', $validated['course_id'])
                        ->where('label', $validated['label'])
                        ->where('amount', $validated['amount'])
                        ->exists();

                    if (!$exists) {
                        MiscCharge::create([
                            'course_id' => $validated['course_id'],
                            'student_id' => $student->id, // Student-specific instance
                            'label' => $validated['label'],
                            'amount' => $validated['amount'],
                            'due_date' => $validated['due_date'] ?? null,
                            'status' => 'pending',
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
                
                $message = 'Miscellaneous charge added to course and applied to all enrolled students.';
            } else {
                $message = 'Global miscellaneous charge added. It can be selected when enrolling students.';
            }

            return redirect()->route('misc-charges.index')
                ->with('success', $message);
        });
    }

    public function edit(MiscCharge $miscCharge): View
    {
        $this->authorize('manage-misc-charges');
        
        // Only allow editing course-level charges (where student_id is null)
        if ($miscCharge->student_id !== null) {
            abort(403, 'Cannot edit student-specific charges. Edit the course-level charge instead.');
        }

        $courses = Course::where('is_active', true)->orderBy('name')->get();

        return view('misc-charges.edit', compact('miscCharge', 'courses'));
    }

    public function update(Request $request, MiscCharge $miscCharge): RedirectResponse
    {
        $this->authorize('manage-misc-charges');
        
        // Only allow editing course-level charges
        if ($miscCharge->student_id !== null) {
            abort(403, 'Cannot edit student-specific charges.');
        }

        $validated = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'], // Allow null for global charges
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($miscCharge, $validated) {
            $oldCourseId = $miscCharge->course_id;
            $newCourseId = $validated['course_id'] ?? null;

            // Update the template charge
            $miscCharge->update([
                'course_id' => $newCourseId,
                'label' => $validated['label'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? null,
            ]);

            // Only auto-apply to students if course_id is set (not global)
            if ($newCourseId) {
                // If course changed, update all student-specific instances
                if ($oldCourseId !== $newCourseId) {
                    // Remove charges from old course students (if old course was not null)
                    if ($oldCourseId) {
                        MiscCharge::where('course_id', $oldCourseId)
                            ->where('student_id', '!=', null)
                            ->where('label', $miscCharge->getOriginal('label'))
                            ->where('status', 'pending')
                            ->delete();
                    }

                    // Add charges to new course students
                    $students = Student::where('course_id', $newCourseId)
                        ->where('status', 'active')
                        ->get();

                    foreach ($students as $student) {
                        MiscCharge::create([
                            'course_id' => $newCourseId,
                            'student_id' => $student->id,
                            'label' => $validated['label'],
                            'amount' => $validated['amount'],
                            'due_date' => $validated['due_date'] ?? null,
                            'status' => 'pending',
                            'created_by' => Auth::id(),
                        ]);
                    }
                } else {
                    // Same course - just update all pending student instances
                    MiscCharge::where('course_id', $newCourseId)
                        ->where('student_id', '!=', null)
                        ->where('label', $miscCharge->getOriginal('label'))
                        ->where('status', 'pending')
                        ->update([
                            'label' => $validated['label'],
                            'amount' => $validated['amount'],
                            'due_date' => $validated['due_date'] ?? null,
                        ]);
                }
            }

            return redirect()->route('misc-charges.index')
                ->with('success', 'Miscellaneous charge updated successfully.');
        });
    }

    public function destroy(MiscCharge $miscCharge): RedirectResponse
    {
        $this->authorize('manage-misc-charges');
        
        // Only allow deleting course-level charges
        if ($miscCharge->student_id !== null) {
            abort(403, 'Cannot delete student-specific charges. Delete the course-level charge instead.');
        }

        return DB::transaction(function () use ($miscCharge) {
            $courseId = $miscCharge->course_id;
            $label = $miscCharge->label;

            // Delete the course-level charge
            $miscCharge->delete();

            // Delete all pending student-specific instances
            MiscCharge::where('course_id', $courseId)
                ->where('student_id', '!=', null)
                ->where('label', $label)
                ->where('status', 'pending')
                ->delete();

            return redirect()->route('misc-charges.index')
                ->with('success', 'Miscellaneous charge deleted and removed from all students.');
        });
    }

    /**
     * API endpoint to fetch available misc charges for a course
     * Used in student creation form dropdown
     */
    public function getAvailableCharges(Request $request)
    {
        $courseId = $request->input('course_id');
        
        // Get global charges (no course_id) and course-specific charges
        $query = MiscCharge::whereNull('student_id'); // Only template charges
        
        if ($courseId) {
            // Get both global and course-specific charges
            $charges = $query->where(function($q) use ($courseId) {
                $q->whereNull('course_id') // Global charges
                  ->orWhere('course_id', $courseId); // Course-specific charges
            })->orderBy('course_id')->orderBy('label')->get();
        } else {
            // Only global charges if no course selected
            $charges = $query->whereNull('course_id')
                ->orderBy('label')
                ->get();
        }
        
        return response()->json($charges->map(function($charge) {
            return [
                'id' => $charge->id,
                'label' => $charge->label,
                'amount' => (float) $charge->amount,
                'due_date' => $charge->due_date ? $charge->due_date->format('Y-m-d') : null,
                'course_id' => $charge->course_id,
                'course_name' => $charge->course ? $charge->course->name : 'Global',
            ];
        }));
    }
}
