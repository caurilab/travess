<?php

declare(strict_types=1);

namespace App\Shared\Context;

use Illuminate\Support\Facades\DB;

/**
 * Porteur du tenant courant pour la durée d'une requête (ou d'un traitement).
 *
 * Enregistré en binding « scoped » : une instance par cycle applicatif (une
 * requête HTTP), réinitialisée entre deux requêtes sous runtime persistant.
 * Le tenant_id y est déposé par le middleware EnsureTenantContext à partir de
 * l'utilisateur authentifié — jamais d'un paramètre client.
 *
 * Double effet, maintenu cohérent :
 *  - en mémoire, pour le scoping Eloquent (TenantScope) ;
 *  - dans le GUC de session PostgreSQL « app.tenant_id », pour la Row-Level
 *    Security (défense en profondeur base, cf. RlsTenant).
 */
final class TenantContext
{
    private ?string $tenantId = null;

    /**
     * Vrai tant qu'une opération système explicitement non scopée est en cours
     * (amorçage d'authentification, super-admin éditeur, maintenance).
     */
    private bool $bypassed = false;

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->definirGuc('app.tenant_id', $tenantId);
    }

    public function forget(): void
    {
        $this->tenantId = null;
        $this->definirGuc('app.tenant_id', '');
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Exécute un traitement sans scoping tenant (échappatoire système explicite).
     *
     * Lève le scoping Eloquent ET la RLS (GUC app.bypass_rls) pour la durée du
     * callback. Réservé aux opérations qui, par nature, précèdent ou transcendent
     * le tenant : résolution de l'utilisateur au login, supervision éditeur tracée.
     * Tout usage doit être justifié et audité — jamais un raccourci pour
     * contourner l'isolation dans un contexte métier.
     */
    public function runBypassed(callable $callback): mixed
    {
        $precedent = $this->bypassed;
        $this->bypassed = true;
        $this->definirGuc('app.bypass_rls', 'on');

        try {
            return $callback();
        } finally {
            $this->bypassed = $precedent;
            $this->definirGuc('app.bypass_rls', $precedent ? 'on' : '');
        }
    }

    /**
     * Positionne un GUC de session PostgreSQL (persistant sur la connexion,
     * hors transaction). Sans effet sur d'autres SGBD (tests unitaires purs).
     */
    private function definirGuc(string $nom, string $valeur): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne('select set_config(?, ?, false)', [$nom, $valeur]);
    }
}
