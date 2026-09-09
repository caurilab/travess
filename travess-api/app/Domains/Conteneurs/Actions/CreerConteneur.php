<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Support\Iso6346;
use Illuminate\Support\Facades\DB;

/**
 * Crée un conteneur. Le numéro est normalisé (forme canonique ISO 6346) avant
 * persistance ; sa validité a déjà été contrôlée par la règle Iso6346Valide.
 */
final class CreerConteneur
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(array $donnees): Conteneur
    {
        return DB::transaction(function () use ($donnees): Conteneur {
            $conteneur = Conteneur::create([
                'bl_id' => $donnees['bl_id'],
                'numero' => Iso6346::normaliser((string) $donnees['numero']),
                'type' => $donnees['type'],
                'statut' => $donnees['statut'] ?? StatutConteneur::ATraiter->value,
                'source_numero' => $donnees['source_numero'] ?? SourceNumeroConteneur::Manuel->value,
            ]);

            $this->auditeur->creation($conteneur, 'conteneur.creation');

            return $conteneur;
        });
    }
}
