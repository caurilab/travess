<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecture des dossiers accessibles au bénéficiaire courant du portail (ADR-013).
 *
 * On lève délibérément le TenantScope — sur le dossier ET sur toute sa
 * projection (bls → conteneurs → parcours), qui appartiennent au tenant du
 * transitaire, pas au workspace du client. La sécurité repose alors ENTIÈREMENT
 * sur la RLS de partage « FOR SELECT » (fail-closed en base) : sans octroi actif
 * ni contexte portail, rien ne remonte. La projection en liste blanche (champs
 * exposés) est faite par les Resources portail, jamais par un modèle brut.
 *
 * Prérequis : contexte portail établi (middleware « portail » → app.portail_user_id).
 */
final class LectureDossiersPartages
{
    /**
     * @return Collection<int, Dossier>
     */
    public function accessibles(): Collection
    {
        return $this->requeteProjetee()->get();
    }

    public function trouver(string $dossierId): ?Dossier
    {
        return $this->requeteProjetee()->whereKey($dossierId)->first();
    }

    /**
     * Requête dossiers + projection partageable, TenantScope levé à chaque
     * niveau (la RLS de partage filtre les lignes réellement octroyées).
     *
     * @return Builder<Dossier>
     */
    private function requeteProjetee(): Builder
    {
        return Dossier::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with(['bls' => function ($query): void {
                $query->withoutGlobalScope(TenantScope::class)
                    ->with(['conteneurs' => function ($query): void {
                        $query->withoutGlobalScope(TenantScope::class)
                            ->with(['suivis' => function ($query): void {
                                $query->withoutGlobalScope(TenantScope::class);
                            }]);
                    }]);
            }]);
    }
}
