<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexte portail (ADR-013) : dépose le bénéficiaire de partage courant dans
 * TenantContext (GUC app.portail_user_id) pour la RLS de partage « FOR SELECT ».
 *
 * Même discipline qu'EnsureTenantContext (principe n°3) : le bénéficiaire est
 * TOUJOURS l'utilisateur authentifié, jamais une entrée client. À appliquer
 * APRÈS auth:sanctum et « tenant » (qui pose app.tenant_id = workspace du client).
 */
final class EnsurePortailContext
{
    /**
     * Clés interdites en entrée : le bénéficiaire ne se fournit jamais côté client.
     */
    private const CLES_INTERDITES = ['portail_user_id', 'beneficiaire', 'beneficiaire_user_id'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::CLES_INTERDITES as $cle) {
            if ($request->route($cle) !== null || $request->has($cle)) {
                abort(422, "Le paramètre « {$cle} » n'est pas accepté : le bénéficiaire est déduit du jeton.");
            }
        }

        $user = $request->user();

        if ($user === null) {
            abort(403, 'Authentification requise pour le portail.');
        }

        $contexte = app(TenantContext::class);
        $contexte->setPortailUser((string) $user->getKey());

        try {
            return $next($request);
        } finally {
            $contexte->forgetPortailUser();
        }
    }
}
