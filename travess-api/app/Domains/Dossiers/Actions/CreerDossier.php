<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Domains\Dossiers\Enums\StatutEtape;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Models\Etape;
use App\Domains\Dossiers\Support\GenerateurReference;
use App\Domains\Dossiers\Support\ModeleWorkflow;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un dossier : référence auto-générée, statut initial, et instanciation
 * (snapshot) des étapes du workflow par défaut selon le sens. Tout est audité
 * dans une seule transaction (ADR-006/007/008).
 */
final class CreerDossier
{
    public function __construct(
        private readonly GenerateurReference $generateurReference,
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{sens: string, client_id: string}  $donnees
     */
    public function executer(array $donnees): Dossier
    {
        $sens = SensDossier::from($donnees['sens']);

        return DB::transaction(function () use ($sens, $donnees): Dossier {
            $dossier = Dossier::create([
                'reference' => $this->generateurReference->suivante($sens),
                'sens' => $sens->value,
                'client_id' => $donnees['client_id'],
                'statut' => StatutDossier::Ouvert->value,
            ]);

            $this->instancierWorkflow($dossier, $sens);

            $this->auditeur->creation($dossier, 'dossier.creation');

            return $dossier->load('etapes', 'client');
        });
    }

    private function instancierWorkflow(Dossier $dossier, SensDossier $sens): void
    {
        $tenant = Tenant::findOrFail($this->tenant->idOrFail());
        $modele = ModeleWorkflow::pour($tenant, $sens);

        $ordre = 1;
        $joursCumules = 0;

        foreach ($modele as $ligne) {
            $joursCumules += $ligne['sla_jours'];

            Etape::create([
                'dossier_id' => $dossier->id,
                'ordre' => $ordre++,
                'libelle' => $ligne['libelle'],
                'sla_jours' => $ligne['sla_jours'],
                // Échéancier prévisionnel : cumul des SLA depuis l'ouverture.
                'date_prevue' => now()->addDays($joursCumules)->toDateString(),
                'statut' => StatutEtape::AFaire->value,
            ]);
        }
    }
}
