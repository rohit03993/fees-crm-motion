<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine if the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can create payments.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin(); // Only admin can update
    }

    /**
     * Determine if the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin(); // Only admin can delete
    }

    /**
     * Determine if the user can approve the payment.
     */
    public function approve(User $user, Payment $payment): bool
    {
        return $user->isAdmin(); // Only admin can approve
    }
}

