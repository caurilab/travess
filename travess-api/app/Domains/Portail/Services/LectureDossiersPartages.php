<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecture des dossiers accessibles au bénéficiaire courant du portail (ADR-013).
 *
 * On lève délibérément le TenantScope (le dossier partagé appartient à un AUTRE
 * tenant que le workspace du client) : la sécurité repose alors sur la RLS de
 * partage « FOR SELECT » (dossier octroyé à app.portail_user_id) — fail-closed
 * en base. On ne réutilise JAMAIS la surface agent : ce read model n'expose que
 * ce que la RLS laisse passer, et les Resources portail projetteront en liste
 * blanche (BL + parcours) au sous-lot 7.1.
 *
 * Prérequis : le contexte portail doit être établi (TenantContext::setPortailUser
 * via le middleware portail). Sans lui, la RLS ne renvoie rien.
 */
final class LectureDossiersPartages
{
    /**
     * @return Collection<int, Dossier>
     */
    public function accessibles(): Collection
    {
        return Dossier::query()
            ->withoutGlobalScope(TenantScope::class)
            ->get();
    }

    public function trouver(string $dossierId): ?Dossier
    {
        return Dossier::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($dossierId)
            ->first();
    }
}
