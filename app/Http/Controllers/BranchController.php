<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $branches = Branch::withCount('students')->orderBy('name')->paginate(15);

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        return view('branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:branches,code'],
            'address' => ['nullable', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        Branch::create($validated);

        return redirect()->route('branches.index')->with('success', 'Branch added successfully.');
    }

    public function edit(Branch $branch): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => ['nullable', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;

        $branch->update($validated);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        
        // Check if branch has students
        if ($branch->students()->count() > 0) {
            return redirect()->route('branches.index')
                ->with('error', 'Cannot delete branch. There are ' . $branch->students()->count() . ' student(s) enrolled in this branch.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }
}

