<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);
        
        $users = User::where('role', 'staff')
            ->withCount('students')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);
        
        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        
        // Generate a random password if not provided
        $password = $request->filled('password') ? $request->password : Str::random(12);
        
        // Generate unique staff ID
        $staffId = $this->generateStaffId();
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'display_password' => $password, // Store plain text for admin viewing
            'role' => 'staff',
            'staff_id' => $staffId,
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Staff member created successfully.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);
        
        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active'),
        ];

        // Update password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
            $data['display_password'] = $request->password; // Store plain text for admin viewing
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', 'Staff member updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        
        // Prevent deleting admin users
        if ($user->isAdmin()) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Cannot delete admin users.');
        }

        // Check if user has created students
        $studentCount = $user->students()->count();
        if ($studentCount > 0) {
            return redirect()
                ->route('users.index')
                ->with('error', "Cannot delete staff member. They have created {$studentCount} student(s).");
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Staff member deleted successfully.');
    }


    private function generateStaffId(): string
    {
        do {
            $staffId = 'STF-' . Str::upper(Str::random(6));
        } while (User::where('staff_id', $staffId)->exists());

        return $staffId;
    }
}

