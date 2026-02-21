<?php

namespace App\Policies;

use App\Models\Edition;
use App\Models\User;

class EditionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function view(User $user, Edition $edition): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, Edition $edition): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function publish(User $user, Edition $edition): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, Edition $edition): bool
    {
        return $user->hasRole('admin');
    }
}
