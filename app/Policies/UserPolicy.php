<?php

namespace App\Policies;

use App\Models\User;

/**
 * Manajemen pengguna hanya untuk superadmin.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Mengubah peran / mereset 2FA pengguna lain; superadmin tidak boleh
     * menurunkan perannya sendiri agar tidak terkunci dari sistem.
     */
    public function changeRole(User $user, User $target): bool
    {
        return $user->isSuperadmin() && $user->isNot($target);
    }
}
