<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde-fou multi-tenant sur les routes authentifiées.
 *
 * Filet de sécurité (couche transport) qui vient APRÈS auth:sanctum :
 *  - rejette (422) toute requête portant un tenant_id/tenant en entrée — le
 *    tenant ne se fournit jamais côté client (principe non négociable n°3) ;
 *  - refuse (403) toute session authentifiée sans tenant résoluble ;
 *  - dépose le tenant de l'utilisateur dans TenantContext pour le global scope.
 *
 * La vraie défense reste le TenantScope au niveau modèle ; ce middleware est
 * une couche supplémentaire, pas l'unique protection.
 */
final class EnsureTenantContext
{
    /**
     * Clés interdites en entrée client, quelle que soit leur source.
     */
    private const CLES_INTERDITES = ['tenant_id', 'tenant'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::CLES_INTERDITES as $cle) {
            // $request->has() couvre la source d'input unifiée : query string,
            // formulaire ET corps JSON (le cas normal d'une API).
            if ($request->route($cle) !== null || $request->has($cle)) {
                abort(422, "Le paramètre « {$cle} » n'est pas accepté : le tenant est déduit du jeton, jamais fourni par le client.");
            }
        }

        $user = $request->user();

        if ($user === null || empty($user->tenant_id)) {
            abort(403, 'Aucun tenant associé à cette session.');
        }

        $contexte = app(TenantContext::class);
        $contexte->set((string) $user->tenant_id);

        try {
            return $next($request);
        } finally {
            // Ceinture supplémentaire : on ne laisse jamais traîner le contexte
            // au-delà de la requête (utile sous runtime persistant).
            $contexte->forget();
        }
    }
}
