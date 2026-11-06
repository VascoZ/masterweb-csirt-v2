<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Alur editorial: penulis membuat & menyunting draf miliknya sendiri,
 * admin yang menerbitkan, menyunting artikel terbit, dan menghapus.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $user->isAdmin() || $this->owns($user, $post);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->isAdmin() || ($this->owns($user, $post) && ! $post->published);
    }

    public function publish(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->id;
    }
}
