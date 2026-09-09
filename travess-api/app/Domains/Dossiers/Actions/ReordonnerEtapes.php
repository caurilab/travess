<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réordonne les étapes d'un dossier selon la liste d'identifiants fournie
 * (position = rang dans la liste). Tout ou rien, audité.
 */
final class ReordonnerEtapes
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  list<string>  $ordreIds  identifiants des étapes, dans l'ordre voulu
     */
    public function executer(Dossier $dossier, array $ordreIds): Dossier
    {
        $etapesExistantes = $dossier->etapes()->pluck('id')->all();

        // La liste doit être exactement l'ensemble des étapes du dossier.
        if (count($ordreIds) !== count($etapesExistantes)
            || array_diff($etapesExistantes, $ordreIds) !== []
        ) {
            throw ValidationException::withMessages([
                'ordre' => [__('La liste doit contenir exactement toutes les étapes du dossier.')],
            ]);
        }

        return DB::transaction(function () use ($dossier, $ordreIds): Dossier {
            foreach ($ordreIds as $index => $id) {
                $dossier->etapes()->whereKey($id)->update(['ordre' => $index + 1]);
            }

            $this->auditeur->enregistrer(
                'dossier',
                $dossier->id,
                'etape.reordonnee',
                null,
                ['ordre' => $ordreIds],
            );

            return $dossier->load('etapes');
        });
    }
}
