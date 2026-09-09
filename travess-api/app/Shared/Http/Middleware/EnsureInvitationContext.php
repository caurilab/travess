<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexte de réclamation d'invitation (ADR-013), chemin PUBLIC.
 *
 * Dépose l'empreinte (hash) du token présenté dans le GUC
 * app.invitation_token_hash pour que la RLS de invitation_portail borne la
 * lecture à CETTE seule ligne — sans bypass en bloc. Le token vient du segment
 * de chemin de l'URL signée (jamais d'un app.invitation_token_hash fourni
 * directement). Purge en fin de requête.
 */
final class EnsureInvitationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        if ($token === '') {
            abort(404);
        }

        $contexte = app(TenantContext::class);

        // Chemin public sans middleware tenant : on part d'un état GUC vide
        // (fail-closed) avant de borner la lecture au seul token présenté.
        $contexte->reinitialiser();
        $contexte->setInvitationToken(hash('sha256', $token));

        try {
            return $next($request);
        } finally {
            $contexte->forgetInvitationToken();
        }
    }
}
