<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Models\User;
use App\Shared\Context\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Lecture des utilisateurs d'un tenant (gestion réservée au gérant, PRD §1.2).
 *
 * User n'étant pas auto-scopé (amorçage auth), l'isolation repose ici sur trois
 * garde-fous explicites : le scope forTenant, la policy, et le route-model
 * binding scopé (cf. User::resolveRouteBinding). Tests dédiés dans
 * tests/Feature/Identity/UserIsolationTest.
 */
final class UserController
{
    public function index(Request $requete): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::forTenant(app(TenantContext::class)->idOrFail())
            ->orderBy('nom')
            ->get();

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }
}
