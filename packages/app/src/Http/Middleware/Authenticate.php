<?php

namespace Filament\Http\Middleware;

use Filament\Context;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        abort_if(
            $user instanceof FilamentUser ?
                (! $user->canAccessFilament()) :
                (config('app.env') !== 'local'),
            403,
        );

        $context = Filament::getCurrentContext();

        if (! $context->hasTenancy()) {
            return;
        }

        $this->setTenant($request, $context);
    }

    protected function setTenant(Request $request, Context $context): void
    {
        /** @var Model $user */
        $user = $context->auth()->user();

        if (! $context->hasRoutableTenancy()) {
            Filament::setTenant($user);

            return;
        }

        if (! $request->route()->hasParameter('tenant')) {
            return;
        }

        $tenant = $context->getTenant($request->route()->parameter('tenant'));

        if ($user instanceof HasTenants && $user->canAccessTenant($tenant)) {
            Filament::setTenant($tenant);

            return;
        }

        abort(404);
    }

    protected function redirectTo($request): string
    {
        return Filament::getUrl();
    }
}