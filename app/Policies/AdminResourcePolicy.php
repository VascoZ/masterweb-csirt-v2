<?php

namespace App\Policies;

use App\Models\User;

/**
 * Resource yang hanya boleh dikelola admin/superadmin.
 */
abstract class AdminResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }
}
