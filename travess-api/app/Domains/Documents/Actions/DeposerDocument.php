<?php

declare(strict_types=1);

namespace App\Domains\Documents\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Documents\Enums\OrigineDocument;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Models\Document;
use App\Shared\Context\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Dépose un document : stocke le fichier (disque objet) et enregistre ses
 * métadonnées. Aucune extraction n'est déclenchée ici (l'ingestion IA est le
 * Lot 5) : le statut d'ingestion reste « none ».
 */
final class DeposerDocument
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{dossier_id: string, type: string}  $donnees
     */
    public function executer(array $donnees, UploadedFile $fichier, OrigineDocument $origine = OrigineDocument::UploadWeb): Document
    {
        return DB::transaction(function () use ($donnees, $fichier, $origine): Document {
            $chemin = $fichier->store('documents/'.$this->tenant->idOrFail());

            $document = Document::create([
                'dossier_id' => $donnees['dossier_id'],
                'type' => $donnees['type'],
                'chemin_stockage' => $chemin,
                'origine' => $origine->value,
                'statut_ingestion' => StatutIngestion::None->value,
                'nom_original' => $fichier->getClientOriginalName(),
                'mime' => $fichier->getClientMimeType(),
                'taille' => $fichier->getSize(),
            ]);

            $this->auditeur->creation($document, 'document.depot');

            return $document;
        });
    }
}
