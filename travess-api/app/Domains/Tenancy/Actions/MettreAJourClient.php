<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Tenancy\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour un client (nom, contact, canaux). Audité. N'affecte jamais
 * est_self (statut d'origine du client, hors du périmètre agent).
 */
final class MettreAJourClient
{
    public function __construct(private readonly Auditeur $auditeur) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Client $client, array $donnees): Client
    {
        return DB::transaction(function () use ($client, $donnees): Client {
            $avant = $client->only(['nom', 'contact', 'canaux']);

            if (array_key_exists('nom', $donnees)) {
                $client->nom = $donnees['nom'];
            }
            if (array_key_exists('contact', $donnees)) {
                $client->contact = $donnees['contact'];
            }
            if (array_key_exists('canaux', $donnees)) {
                $client->canaux = array_filter($donnees['canaux'] ?? []);
            }
            $client->save();

            if ($client->wasChanged()) {
                $this->auditeur->miseAJour($client, 'client.mise_a_jour', $avant);
            }

            return $client;
        });
    }
}
