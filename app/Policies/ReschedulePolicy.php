<?php

namespace App\Policies;

use App\Models\Reschedule;
use App\Models\User;

class ReschedulePolicy
{
    /**
     * Determine if the user can view any reschedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can view the reschedule.
     */
    public function view(User $user, Reschedule $reschedule): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can create reschedules.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the reschedule.
     */
    public function update(User $user, Reschedule $reschedule): bool
    {
        return $user->isAdmin(); // Only admin can approve/reject
    }

    /**
     * Determine if the user can delete the reschedule.
     */
    public function delete(User $user, Reschedule $reschedule): bool
    {
        return $user->isAdmin(); // Only admin can delete
    }

    /**
     * Determine if the user can approve the reschedule.
     */
    public function approve(User $user, Reschedule $reschedule): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can reject the reschedule.
     */
    public function reject(User $user, Reschedule $reschedule): bool
    {
        return $user->isAdmin();
    }
}

