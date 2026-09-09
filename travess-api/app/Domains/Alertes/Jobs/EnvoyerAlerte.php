<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Jobs;

use App\Domains\Alertes\Canaux\FabriqueCanal;
use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Alertes\Models\Notification;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Shared\Jobs\JobTenantScoped;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Envoie une alerte sur les canaux configurés, à ses destinataires (agents
 * assignés au dossier + gérants du tenant). Chaque (destinataire, canal) est
 * journalisé dans `notification` ; `alerte.canaux_envoyes` est mis à jour.
 * S'exécute dans le contexte du tenant (JobTenantScoped).
 */
final class EnvoyerAlerte extends JobTenantScoped
{
    public function __construct(string $tenantId, public readonly string $alerteId)
    {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        $alerte = Alerte::with('dossier.agents')->find($this->alerteId);
        if ($alerte === null) {
            return;
        }

        $fabrique = app(FabriqueCanal::class);
        $canauxEnvoyes = $alerte->canaux_envoyes;

        foreach ($this->destinataires($alerte) as $destinataire) {
            foreach ($this->canaux($destinataire) as $canal) {
                $statut = $fabrique->pour($canal)->envoyer($destinataire, $alerte);

                Notification::create([
                    'destinataire_id' => $destinataire->id,
                    'canal' => $canal->value,
                    'type_evenement' => $alerte->type->value,
                    'statut' => $statut->value,
                    'sujet' => "Alerte {$alerte->type->value}",
                    'meta' => ['alerte_id' => $alerte->id],
                    'envoye_at' => $statut === StatutNotification::Envoye ? Carbon::now() : null,
                ]);

                $canauxEnvoyes[] = $canal->value;
            }
        }

        $alerte->forceFill(['canaux_envoyes' => array_values(array_unique($canauxEnvoyes))])->save();
    }

    /**
     * @return Collection<int, User>
     */
    private function destinataires(Alerte $alerte): Collection
    {
        $agents = $alerte->dossier->agents; // agents assignés au dossier
        $gerants = User::query()->forTenant($this->tenantId)
            ->where('role', RoleUtilisateur::Gerant->value)
            ->get();

        return $agents->concat($gerants)->unique('id')->values();
    }

    /**
     * Canaux préférés du destinataire (défaut : e-mail, seul canal réel au Lot 2).
     *
     * @return list<CanalNotification>
     */
    private function canaux(User $destinataire): array
    {
        $prefs = $destinataire->preferences_notif['canaux'] ?? null;
        $valeurs = is_array($prefs) && $prefs !== [] ? $prefs : [CanalNotification::Email->value];

        return array_values(array_filter(array_map(
            static fn (string $v): ?CanalNotification => CanalNotification::tryFrom($v),
            $valeurs,
        )));
    }
}
