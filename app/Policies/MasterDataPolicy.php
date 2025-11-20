<?php

namespace App\Policies;

use App\Models\User;

class MasterDataPolicy
{
    /**
     * Determine if the user can manage courses.
     */
    public function manageCourses(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage branches.
     */
    public function manageBranches(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage banks.
     */
    public function manageBanks(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage misc charges.
     */
    public function manageMiscCharges(User $user): bool
    {
        return $user->isAdmin();
    }
}

