<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine if the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can view the student.
     */
    public function view(User $user, Student $student): bool
    {
        // Admin can view all students
        if ($user->isAdmin()) {
            return true;
        }
        
        // Staff can only view students they created
        if ($user->isStaff()) {
            return $student->created_by === $user->id;
        }
        
        return false;
    }

    /**
     * Determine if the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the student.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin(); // Only admin can update
    }

    /**
     * Determine if the user can delete the student.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin(); // Only admin can delete
    }
}

