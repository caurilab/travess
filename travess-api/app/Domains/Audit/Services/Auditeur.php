<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Écrit le journal d'audit (ADR-007). Appelé explicitement depuis les Actions
 * de mutation, dans la même transaction que la mutation elle-même.
 *
 * `tenant_id` est forcé par BelongsToTenant depuis le contexte ; `user_id` vient
 * de l'utilisateur authentifié (null pour une écriture système/queue), jamais
 * du client.
 */
final class Auditeur
{
    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public function enregistrer(
        string $entite,
        string $entiteId,
        string $action,
        ?array $avant = null,
        ?array $apres = null,
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'entite' => $entite,
            'entite_id' => $entiteId,
            'action' => $action,
            'avant' => $avant,
            'apres' => $apres,
            'at' => now(),
        ]);
    }

    /**
     * Trace la création d'un modèle (apres = ses attributs).
     */
    public function creation(Model $modele, string $action): void
    {
        $this->enregistrer($this->entite($modele), (string) $modele->getKey(), $action, null, $modele->attributesToArray());
    }

    /**
     * Trace une mise à jour (avant/après restreints aux attributs modifiés).
     *
     * @param  array<string, mixed>  $avant  valeurs d'origine des champs modifiés
     */
    public function miseAJour(Model $modele, string $action, array $avant): void
    {
        $this->enregistrer(
            $this->entite($modele),
            (string) $modele->getKey(),
            $action,
            $avant,
            $modele->getChanges(),
        );
    }

    private function entite(Model $modele): string
    {
        return Str::snake(class_basename($modele));
    }
}
