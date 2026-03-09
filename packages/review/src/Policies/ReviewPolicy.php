<?php

namespace Lunar\Review\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Lunar\Admin\Models\Staff;
use Lunar\Review\Models\Review;

class ReviewPolicy
{
    /**
     * The permission required to manage reviews.
     */
    private const PERMISSION = 'sales:reviews:manage';

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable|Staff|null $user): bool
    {
        if ($user instanceof Staff) {
            return $this->staffHasPermission($user);
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable|Staff|null $user, Review $review): bool
    {
        if ($user instanceof Staff) {
            return $this->staffHasPermission($user);
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Staff $user): bool
    {
        return $this->staffHasPermission($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Staff $user, Review $review): bool
    {
        return $this->staffHasPermission($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Staff $user, Review $review): bool
    {
        return $this->staffHasPermission($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Staff $user, Review $review): bool
    {
        return $this->staffHasPermission($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Staff $user, Review $review): bool
    {
        return $this->staffHasPermission($user);
    }

    /**
     * Determine if the given staff member can manage reviews.
     */
    private function staffHasPermission(Staff $user): bool
    {
        return $user->can(self::PERMISSION);
    }
}
