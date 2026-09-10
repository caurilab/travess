<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Jobs;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Surestaries\Services\GenererAlertes;
use App\Domains\Surestaries\Services\RecalculFranchise;
use App\Domains\Surestaries\Support\CalculFranchise;
use App\Domains\Tracking\Contracts\FournisseurTracking;
use App\Domains\Tracking\Data\SuiviConteneurData;
use App\Domains\Tracking\Services\DecompteConsommationTracking;
use App\Domains\Tracking\Services\GardeQuotaTracking;
use App\Domains\Tracking\Support\CalculProchainPoll;
use App\Domains\Tracking\Support\MappeurStatutConteneur;
use App\Shared\Jobs\JobTenantScoped;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Interroge le tracking d'UN conteneur (unité = 1 appel facturé). Séquence
 * disciplinée (docs/08, docs/09) : verrou anti-doublon, garde de conteneur
 * pollable (actif, trackable), plafond de quota, comptage de conso, appel
 * fournisseur, écriture de snapshot idempotente, mapping de statut, puis
 * recalcul des surestaries + alertes. Tenant-scopé.
 */
final class PollerConteneur extends JobTenantScoped
{
    public function __construct(string $tenantId, public readonly string $conteneurId, public readonly bool $force = false)
    {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        // Sérialise poll planifié et rafraîchissement à la demande concurrents
        // (sinon deux appels facturés pour rien — fenêtre TOCTOU).
        DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$this->conteneurId]);

        $conteneur = Conteneur::query()->with(['bl.armateur', 'franchises', 'suivis'])->find($this->conteneurId);
        if ($conteneur === null || $conteneur->statut === StatutConteneur::Rendu) {
            return;
        }

        $dernier = $conteneur->suivis->sortByDesc('captured_at')->first();

        // Re-vérif d'échéance (rejeu at-least-once) : un poll planifié ne doit pas
        // s'exécuter avant l'heure. Un rafraîchissement à la demande force.
        if (! $this->force && $dernier?->prochain_poll_prevu !== null && $dernier->prochain_poll_prevu->isFuture()) {
            return;
        }

        $armateur = $conteneur->bl->armateur;

        // Grimaldi & armateurs non couverts : aucun appel JSONCargo (économie) —
        // bascule IMAP/manuel, plus de poll automatique (docs/09 §1.1).
        if (! $armateur->trackable || $armateur->nom_api === null) {
            $this->basculer($conteneur, $dernier, SourceSuiviTracking::Manuel, null);

            return;
        }

        // Plafond de sécurité global : au-delà de ~90 %, bascule IMAP/manuel.
        if (! app(GardeQuotaTracking::class)->autoriseAppel()) {
            $this->basculer($conteneur, $dernier, SourceSuiviTracking::Imap, CarbonImmutable::now()->addDay());

            return;
        }

        $conso = app(DecompteConsommationTracking::class);
        $conso->compter();

        try {
            $suivi = app(FournisseurTracking::class)->suivreConteneur($conteneur->numero, (string) $armateur->nom_api);
        } catch (Throwable $e) {
            $conso->liberer(); // appel non abouti : on rend l'unité
            throw $e;
        }

        // Snapshot inchangé : on ne re-déclenche ni recalcul ni alerte (docs/08 §7).
        if ($dernier !== null && ($dernier->snapshot['_empreinte'] ?? null) === $suivi->empreinte()) {
            $dernier->forceFill([
                'prochain_poll_prevu' => $this->prochainPoll($conteneur, $suivi),
                'captured_at' => $suivi->capturedAt,
            ])->save();

            return;
        }

        $this->enregistrerEtAppliquer($conteneur, $suivi);
    }

    private function enregistrerEtAppliquer(Conteneur $conteneur, SuiviConteneurData $suivi): void
    {
        DB::transaction(function () use ($conteneur, $suivi): void {
            $nouveauStatut = MappeurStatutConteneur::versStatut($suivi->phase);

            $conteneur->suivis()->create([
                'source' => SourceSuiviTracking::Jsoncargo->value,
                'snapshot' => [...$suivi->snapshotBrut, '_empreinte' => $suivi->empreinte()],
                'statut_conteneur' => $suivi->statutBrut,
                'emplacement' => $suivi->emplacement,
                'eta_destination' => $suivi->etaDestination,
                'navire_nom' => $suivi->navireNom,
                'navire_imo' => $suivi->navireImo,
                'prochain_poll_prevu' => $this->prochainPoll($conteneur, $suivi, $nouveauStatut),
                'captured_at' => $suivi->capturedAt,
            ]);

            // Le tracking est FACTUEL et autoritaire : il met à jour le statut
            // (contrairement à l'IA, principe n°5). Report du navire (IMO fiable,
            // principe n°7) sur le BL s'il manque.
            if ($conteneur->bl->navire_imo === null && $suivi->navireImo !== null) {
                $conteneur->bl->forceFill(['navire_nom' => $suivi->navireNom, 'navire_imo' => $suivi->navireImo])->save();
            }

            if ($conteneur->statut !== $nouveauStatut) {
                $avant = ['statut' => $conteneur->statut->value];
                $conteneur->forceFill(['statut' => $nouveauStatut->value])->save();
                app(Auditeur::class)->miseAJour($conteneur, 'conteneur.statut_tracking', $avant);

                $this->recalculerSurestaries($conteneur);
            }
        });
    }

    /** Recalcule les franchises du conteneur puis (ré)génère les alertes du tenant. */
    private function recalculerSurestaries(Conteneur $conteneur): void
    {
        $recalcul = app(RecalculFranchise::class);
        $conteneur->load('franchises.conteneur.bl.armateur');

        $conteneur->franchises->each(function ($franchise) use ($recalcul): void {
            $recalcul->recalculer($franchise);
            if ($franchise->isDirty()) {
                $franchise->save();
            }
        });

        app(GenererAlertes::class)->pourTenantCourant();
    }

    private function prochainPoll(Conteneur $conteneur, SuiviConteneurData $suivi, ?StatutConteneur $statut = null): ?CarbonImmutable
    {
        $statut ??= MappeurStatutConteneur::versStatut($suivi->phase);

        $franchiseActive = $conteneur->franchises->contains(
            fn ($franchise): bool => CalculFranchise::estActif($franchise->type->value, $statut->value),
        );

        return CalculProchainPoll::calculer($suivi->phase, $franchiseActive, $suivi->etaDestination, CarbonImmutable::now());
    }

    /** Écrit un marqueur de bascule (source non-JSONCargo) et (re)programme le poll. */
    private function basculer(Conteneur $conteneur, ?SuiviTracking $dernier, SourceSuiviTracking $source, ?CarbonImmutable $prochain): void
    {
        // Idempotence : si le dernier suivi est déjà ce marqueur, on ne
        // réécrit pas (évite d'empiler des lignes identiques).
        if ($dernier !== null && $dernier->source === $source) {
            $dernier->forceFill(['prochain_poll_prevu' => $prochain])->save();

            return;
        }

        $conteneur->suivis()->create([
            'source' => $source->value,
            'snapshot' => ['bascule' => $source->value],
            'prochain_poll_prevu' => $prochain,
            'captured_at' => CarbonImmutable::now(),
        ]);
    }
}
