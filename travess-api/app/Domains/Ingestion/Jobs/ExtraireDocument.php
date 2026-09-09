<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Ingestion\Contracts\ExtracteurDocument;
use App\Domains\Ingestion\Data\DocumentAExtraire;
use App\Domains\Ingestion\Schemas\RegistreSchemas;
use App\Domains\Ingestion\Services\DecompteConsommationIa;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Jobs\JobTenantScoped;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Extraction IA d'un document, en file (principe n°4) et dans le contexte du
 * tenant (JobTenantScoped). L'IA propose seulement : rien n'est appliqué au
 * dossier ici (l'application se fait à la validation humaine, principe n°5).
 *
 * Le job est idempotent : les files sont at-least-once (redélivrance possible
 * après crash worker). Une extraction déjà réussie n'est pas retraitée — sinon
 * on rappellerait le fournisseur payant et on décompterait deux fois.
 */
final class ExtraireDocument extends JobTenantScoped
{
    public function __construct(
        string $tenantId,
        public readonly string $documentId,
        public readonly string $extractionId,
    ) {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        $document = Document::find($this->documentId);
        $extraction = ExtractionIa::find($this->extractionId);

        if ($document === null || $extraction === null) {
            return;
        }

        // Rejeu at-least-once : déjà traité ⇒ on ne rappelle pas le fournisseur.
        if ($extraction->statut === StatutExtraction::Reussi) {
            return;
        }

        // L'opt-in a pu être retiré entre le lancement et l'exécution (file
        // longue) : on ne transmet alors rien au fournisseur.
        if (! $this->iaActivee()) {
            $this->marquerEchec($document, $extraction, 'extraction.abandonnee');

            return;
        }

        try {
            $schema = app(RegistreSchemas::class)->pour($document->type->value);
            $aExtraire = new DocumentAExtraire(
                (string) config('ia.disque'),
                $document->chemin_stockage,
                $document->mime,
                $document->type->value,
            );

            $resultat = app(ExtracteurDocument::class)->extraire($aExtraire, $schema);

            DB::transaction(function () use ($document, $extraction, $resultat): void {
                $extraction->forceFill([
                    'statut' => StatutExtraction::Reussi->value,
                    'champs' => $resultat->champsToArray(),
                ])->save();

                $document->forceFill(['statut_ingestion' => StatutIngestion::Extrait->value])->save();

                // L'unité a été réservée au lancement : on n'enregistre ici que
                // le coût réel (pas de nouvel increment du compteur).
                app(DecompteConsommationIa::class)->enregistrer($extraction, $resultat->unitesConsommees);

                app(Auditeur::class)->enregistrer(
                    'extraction_ia',
                    (string) $extraction->getKey(),
                    'extraction.reussie',
                    null,
                    ['cout_unite' => $resultat->unitesConsommees],
                );
            });
        } catch (Throwable $e) {
            // Observabilité : on remonte l'exception au gestionnaire d'erreurs
            // (Sentry/handler), jamais dans les logs applicatifs bruts — elle
            // peut contenir des en-têtes/clé du fournisseur.
            report($e);

            $this->marquerEchec($document, $extraction, 'extraction.echouee');
        }
    }

    /**
     * Marque l'échec (document re-lançable) et libère l'unité réservée.
     */
    private function marquerEchec(Document $document, ExtractionIa $extraction, string $action): void
    {
        DB::transaction(function () use ($document, $extraction, $action): void {
            $extraction->forceFill(['statut' => StatutExtraction::Echoue->value])->save();
            $document->forceFill(['statut_ingestion' => StatutIngestion::None->value])->save();

            app(DecompteConsommationIa::class)->liberer();

            app(Auditeur::class)->enregistrer('extraction_ia', (string) $extraction->getKey(), $action);
        });
    }

    private function iaActivee(): bool
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return false;
        }

        return (bool) ($tenant->parametres[(string) config('ia.opt_in_parametre')] ?? false);
    }
}
