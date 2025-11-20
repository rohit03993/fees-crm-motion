<?php

namespace App\Policies;

use App\Models\User;

class SettingsPolicy
{
    /**
     * Determine if the user can view settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update settings.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage penalty settings.
     */
    public function managePenalties(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can clear all students.
     */
    public function clearStudents(User $user): bool
    {
        return $user->isAdmin();
    }
}

