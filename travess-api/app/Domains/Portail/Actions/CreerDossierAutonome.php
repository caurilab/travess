<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Dossiers\Actions\CreerDossier;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Services\FindOrCreateClientAutonome;

/**
 * Crée un dossier autonome dans le workspace du client (ADR-013, 7.3a) : fiche
 * self obtenue/créée, puis délégation à CreerDossier (référence, workflow,
 * audit) en forçant la posture « autonome ». Aucun acces_dossier : le dossier
 * vit dans le tenant du client (RLS tenant normale).
 */
final class CreerDossierAutonome
{
    public function __construct(
        private readonly FindOrCreateClientAutonome $ficheSelf,
        private readonly CreerDossier $creerDossier,
    ) {}

    /**
     * @param  array{sens: string}  $donnees
     */
    public function executer(array $donnees, string $nomClient): Dossier
    {
        $client = $this->ficheSelf->executer($nomClient);

        return $this->creerDossier->executer([
            'sens' => $donnees['sens'],
            'client_id' => $client->id,
            'posture' => PostureDossier::Autonome->value,
        ]);
    }
}
