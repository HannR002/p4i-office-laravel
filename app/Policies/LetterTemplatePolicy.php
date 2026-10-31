<?php

namespace App\Policies;

use App\Models\LetterTemplate;
use App\Models\User;

class LetterTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, LetterTemplate $template): bool
    {
        return $user->isActive() && $user->organization_id === $template->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->isSystemAdmin();
    }

    public function update(User $user, LetterTemplate $template): bool
    {
        return $user->isActive()
            && $user->organization_id === $template->organization_id
            && $user->isSystemAdmin();
    }

    public function delete(User $user, LetterTemplate $template): bool
    {
        return $user->isActive()
            && $user->organization_id === $template->organization_id
            && $user->isSystemAdmin();
    }
}
