<?php

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;

class AdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Ad $ad): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Ad $ad): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Ad $ad): bool
    {
        return $user->hasRole('admin');
    }
}
