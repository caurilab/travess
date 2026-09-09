<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Applicateurs;

use App\Domains\Conteneurs\Actions\CreerBl;
use App\Domains\Conteneurs\Actions\CreerConteneur;
use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Support\Iso6346;
use App\Domains\Documents\Models\Document;
use App\Domains\Ingestion\Contracts\ApplicateurExtraction;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Crée un BL (et ses conteneurs) à partir d'une extraction de connaissement
 * validée. Les données obligatoires que l'IA ne peut pas fournir de façon fiable
 * (armateur_id via l'annuaire, numéro) doivent être complétées par l'humain à la
 * validation ; à défaut, on refuse (422) plutôt que d'écrire un BL incomplet.
 */
final class ApplicateurBl implements ApplicateurExtraction
{
    public function __construct(
        private readonly CreerBl $creerBl,
        private readonly CreerConteneur $creerConteneur,
    ) {}

    public function appliquer(Document $document, array $donnees): array
    {
        $numero = $this->texte($donnees['numero_bl'] ?? $donnees['numero'] ?? null);
        $armateurId = $this->texte($donnees['armateur_id'] ?? null);

        if ($numero === null || $armateurId === null) {
            throw new HttpException(422, 'Numéro de BL et armateur sont requis pour créer le BL.');
        }

        $bl = $this->creerBl->executer([
            'dossier_id' => $document->dossier_id,
            'numero' => $numero,
            'armateur_id' => $armateurId,
            'navire_nom' => $this->texte($donnees['navire_nom'] ?? null),
            'navire_imo' => $this->texte($donnees['navire_imo'] ?? null),
        ]);

        $crees = ['BL '.$bl->numero];

        foreach ($this->conteneurs($donnees) as $conteneur) {
            $numeroConteneur = $this->texte($conteneur['numero'] ?? null);
            $type = $this->texte($conteneur['type'] ?? null);

            if ($numeroConteneur === null || $type === null || ! Iso6346::estValide($numeroConteneur)) {
                continue;
            }

            $c = $this->creerConteneur->executer([
                'bl_id' => $bl->id,
                'numero' => $numeroConteneur,
                'type' => $type,
                'source_numero' => SourceNumeroConteneur::Ia->value,
            ]);

            $crees[] = 'Conteneur '.$c->numero;
        }

        return $crees;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return list<array<string, mixed>>
     */
    private function conteneurs(array $donnees): array
    {
        $liste = $donnees['conteneurs'] ?? [];

        if (! is_array($liste)) {
            return [];
        }

        return array_values(array_filter($liste, 'is_array'));
    }

    private function texte(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }
}
