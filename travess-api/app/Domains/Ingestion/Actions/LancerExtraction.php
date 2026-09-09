<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Ingestion\Jobs\ExtraireDocument;
use App\Domains\Ingestion\Services\DecompteConsommationIa;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Déclenche l'extraction IA d'un document : vérifie l'opt-in et le quota
 * (plafond dur, principe n°6 appliqué à l'IA), garantit l'idempotence, puis met
 * le travail en file (principe n°4). L'IA propose seulement (principe n°5) :
 * cette action ne modifie jamais le dossier.
 */
final class LancerExtraction
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly DecompteConsommationIa $decompte,
        private readonly TenantContext $tenant,
    ) {}

    public function executer(Document $document): ExtractionIa
    {
        $tenant = $this->tenantCourant();

        if (! $this->iaActivee($tenant)) {
            throw new HttpException(403, "L'ingestion IA n'est pas activée pour ce compte.");
        }

        return DB::transaction(function () use ($document, $tenant): ExtractionIa {
            // Sérialise les lancements concurrents du MÊME document : sans ce
            // verrou, deux requêtes quasi simultanées ne voient aucune extraction
            // en cours et déclenchent deux appels IA payants (fenêtre TOCTOU).
            // Verrou consultatif de transaction : relâché au commit/rollback.
            DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [(string) $document->getKey()]);

            // Idempotence : une extraction déjà en file OU réussie non validée est
            // renvoyée telle quelle — pas de nouveau job, pas de nouveau décompte.
            // Une relance n'est légitime qu'après un échec (statut none).
            $existante = $document->extractions()
                ->whereIn('statut', [StatutExtraction::EnFile->value, StatutExtraction::Reussi->value])
                ->whereNull('valide_at')
                ->latest()
                ->first();

            if ($existante !== null) {
                return $existante;
            }

            // Réservation atomique du quota (plafond dur). Rollback de la
            // transaction ⇒ réservation annulée si la suite échoue.
            if (! $this->decompte->reserver($tenant->quota_ia_mensuel)) {
                throw new HttpException(429, 'Quota IA mensuel atteint.');
            }

            $extraction = $document->extractions()->create([
                'statut' => StatutExtraction::EnFile->value,
                'champs' => [],
                'corrections' => [],
                'cout_unite' => 0,
            ]);

            $document->forceFill(['statut_ingestion' => StatutIngestion::EnFile->value])->save();

            $this->auditeur->creation($extraction, 'extraction.lancee');

            ExtraireDocument::dispatch(
                $this->tenant->idOrFail(),
                (string) $document->getKey(),
                (string) $extraction->getKey(),
            )->afterCommit();

            return $extraction;
        });
    }

    private function tenantCourant(): Tenant
    {
        return $this->tenant->runBypassed(
            fn (): Tenant => Tenant::findOrFail($this->tenant->idOrFail()),
        );
    }

    private function iaActivee(Tenant $tenant): bool
    {
        return (bool) ($tenant->parametres[(string) config('ia.opt_in_parametre')] ?? false);
    }
}
