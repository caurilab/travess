<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Ingestion\Applicateurs\FabriqueApplicateur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validation humaine d'une extraction (principe n°5) : l'agent corrige les
 * champs proposés, puis l'écriture métier est appliquée au dossier via
 * l'applicateur du type de document. Tout est audité et transactionnel.
 */
final class ValiderExtraction
{
    public function __construct(
        private readonly FabriqueApplicateur $fabrique,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $corrections  valeurs corrigées par l'humain
     * @return list<string> enregistrements créés
     */
    public function executer(ExtractionIa $extraction, array $corrections): array
    {
        return DB::transaction(function () use ($extraction, $corrections): array {
            // Verrou pessimiste : deux validations concurrentes de la même
            // extraction passeraient sinon toutes deux les gardes et appliqueraient
            // deux fois l'écriture métier (double BL). On relit sous verrou et on
            // re-teste à l'intérieur de la transaction.
            $extraction = $extraction->newQuery()->lockForUpdate()->findOrFail($extraction->getKey());

            if ($extraction->statut !== StatutExtraction::Reussi) {
                throw new HttpException(409, 'Seule une extraction réussie peut être validée.');
            }

            if ($extraction->valide_at !== null) {
                throw new HttpException(409, 'Cette extraction a déjà été validée.');
            }

            $document = $extraction->document()->firstOrFail();

            // Données finales : valeurs extraites écrasées par les corrections
            // humaines. Les champs extraits sont stockés {valeur, confiance, ...}.
            $donnees = [...$this->valeursExtraites($extraction), ...$corrections];

            $applicateur = $this->fabrique->pour($document->type);
            $crees = $applicateur?->appliquer($document, $donnees) ?? [];

            $extraction->forceFill([
                'corrections' => $corrections,
                'valide_par' => Auth::id(),
                'valide_at' => now(),
            ])->save();

            $document->forceFill(['statut_ingestion' => StatutIngestion::Valide->value])->save();

            $this->auditeur->enregistrer(
                'extraction_ia',
                (string) $extraction->getKey(),
                'extraction.validee',
                null,
                ['corrections' => $corrections, 'crees' => $crees],
            );

            return $crees;
        });
    }

    /**
     * Réduit les champs extraits {valeur, confiance, zone_source} à leurs valeurs.
     *
     * @return array<string, mixed>
     */
    private function valeursExtraites(ExtractionIa $extraction): array
    {
        $valeurs = [];

        foreach ($extraction->champs as $nom => $champ) {
            $valeurs[$nom] = is_array($champ) ? ($champ['valeur'] ?? null) : $champ;
        }

        return $valeurs;
    }
}
