<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Models\Bl;
use Illuminate\Support\Facades\DB;

/**
 * Crée un connaissement (BL) rattaché à un dossier. Le navire est identifié par
 * son IMO (clé fiable) ; navire_nom reste indicatif.
 */
final class CreerBl
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(array $donnees): Bl
    {
        return DB::transaction(function () use ($donnees): Bl {
            $bl = Bl::create($donnees);

            $this->auditeur->creation($bl, 'bl.creation');

            return $bl;
        });
    }
}
