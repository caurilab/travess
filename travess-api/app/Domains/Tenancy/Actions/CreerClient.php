<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Tenancy\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Crée un client (donneur d'ordre) créé par l'agence — donc jamais « self »
 * (est_self réservé aux clients issus du portail autonome). Audité.
 */
final class CreerClient
{
    public function __construct(private readonly Auditeur $auditeur) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(array $donnees): Client
    {
        return DB::transaction(function () use ($donnees): Client {
            $client = Client::create([
                'nom' => $donnees['nom'],
                'contact' => $donnees['contact'] ?? null,
                'canaux' => array_filter($donnees['canaux'] ?? []),
                'est_self' => false,
            ]);

            $this->auditeur->creation($client, 'client.creation');

            return $client;
        });
    }
}
