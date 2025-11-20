<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-courses');
        
        $courses = Course::withCount('students')->orderBy('name')->paginate(15);

        return view('courses.index', compact('courses'));
    }

    public function create(): View
    {
        $this->authorize('manage-courses');
        
        return view('courses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-courses');
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:courses,code'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = $validated['is_active'] ?? true;

        Course::create($validated);

        return redirect()->route('courses.index')->with('success', 'Course added successfully.');
    }

    public function edit(Course $course): View
    {
        $this->authorize('manage-courses');
        
        return view('courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manage-courses');
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($course->id)],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;

        $course->update($validated);

        return redirect()->route('courses.index')->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('manage-courses');
        
        // Check if course has students
        if ($course->students()->count() > 0) {
            return redirect()->route('courses.index')
                ->with('error', 'Cannot delete course. There are ' . $course->students()->count() . ' student(s) enrolled in this course.');
        }

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Course deleted successfully.');
    }
}
