<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Tenancy\Enums\TypeTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de la surface « autonome » (ADR-013, 7.3) : réservée aux comptes client
 * (rôle client dans un tenant type=client). À appliquer APRÈS auth:sanctum et
 * « tenant » (qui pose app.tenant_id = workspace du client). Défense en
 * profondeur : le rôle ET le type de tenant sont vérifiés.
 */
final class EnsureAutonomeContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== RoleUtilisateur::Client || $user->tenant->type !== TypeTenant::Client) {
            abort(403, 'Surface réservée aux comptes client autonomes.');
        }

        return $next($request);
    }
}
