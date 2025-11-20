<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    /**
     * Determine if the user can view any discounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can view the discount.
     */
    public function view(User $user, Discount $discount): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can create discounts.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the discount.
     */
    public function update(User $user, Discount $discount): bool
    {
        return $user->isAdmin(); // Only admin can approve/reject
    }

    /**
     * Determine if the user can delete the discount.
     */
    public function delete(User $user, Discount $discount): bool
    {
        return $user->isAdmin(); // Only admin can delete
    }

    /**
     * Determine if the user can approve the discount.
     */
    public function approve(User $user, Discount $discount): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can reject the discount.
     */
    public function reject(User $user, Discount $discount): bool
    {
        return $user->isAdmin();
    }
}

