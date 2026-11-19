<?php

namespace App\View\Composers;

use App\Enums\Role;
use App\Models\Pop;
use Illuminate\View\View;

class StaffOffcanvasComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['roles'])) {
            $user = auth()->user();
            $roles = Role::cases();
            if ($user && ! $user->isSuperAdmin()) {
                $roles = array_filter($roles, fn ($r) => ! in_array($r, [Role::SUPER_ADMIN, Role::NOC], true));
            }
            $view->with('roles', $roles);
        }

        if (! isset($data['pops'])) {
            $view->with('pops', Pop::where('is_active', true)->orderBy('name')->get());
        }
    }
}

