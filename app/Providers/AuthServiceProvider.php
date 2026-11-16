<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Super admins bypass every gate. Granular permissions still govern
        // every other role, and the decision-authority layer is evaluated
        // separately for governed abilities. Only the staff `User` model
        // participates; vendor and customer models authenticate on separate
        // guards and must never be treated as staff.
        Gate::before(function ($user, $ability) {
            return $user instanceof \App\Models\User && $user->hasRole('super_admin')
                ? true
                : null;
        });
    }
}
