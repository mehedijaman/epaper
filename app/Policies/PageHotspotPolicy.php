<?php

namespace App\Policies;

use App\Models\PageHotspot;
use App\Models\User;

class PageHotspotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function view(User $user, PageHotspot $pageHotspot): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function update(User $user, PageHotspot $pageHotspot): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }

    public function delete(User $user, PageHotspot $pageHotspot): bool
    {
        return $user->hasAnyRole(['admin', 'operator']);
    }
}
