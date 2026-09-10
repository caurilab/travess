<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Actions;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Audit\Services\Auditeur;
use App\Domains\Correspondance\Enums\DirectionMessage;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Jobs\EnvoyerMessageCorrespondance;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un message sortant (statut « en file ») et dispatche son envoi (principe
 * n°4 : l'agent n'attend jamais l'externe).
 *
 * Anti-exfiltration / usurpation (audit B1) : quand un armateur est désigné, on
 * envoie EXCLUSIVEMENT à son e-mail de carnet (l'adresse libre est ignorée) ;
 * une adresse libre n'est admise qu'en l'absence d'armateur, et réservée au
 * gérant. L'adresse retenue est figée sur le message (valeur probante).
 * L'humain déclenche explicitement l'envoi (principe n°5).
 */
final class CreerEtEnvoyerMessage
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Dossier $dossier, User $auteur, array $donnees): Message
    {
        $adresse = $this->resoudreAdresse($donnees, $auteur);

        $message = DB::transaction(function () use ($dossier, $auteur, $donnees, $adresse): Message {
            $message = new Message([
                'dossier_id' => $dossier->id,
                'armateur_id' => $donnees['armateur_id'] ?? null,
                'auteur_id' => $auteur->id,
                'direction' => DirectionMessage::Sortant->value,
                'type_demande' => $donnees['type_demande'] ?? null,
                'canal' => $donnees['canal'],
                'destinataire_adresse' => $adresse,
                'objet' => $donnees['objet'],
                'corps' => $donnees['corps'],
                'statut' => StatutMessage::EnFile->value,
                'meta' => [],
            ]);
            $message->save();

            // Audité à la mise en file (l'aboutissement réel est audité par le job).
            $this->auditeur->creation($message, 'correspondance.mise_en_file');

            return $message;
        });

        EnvoyerMessageCorrespondance::dispatch($this->tenant->idOrFail(), (string) $message->getKey())
            ->afterCommit();

        return $message;
    }

    /**
     * Détermine l'adresse d'envoi (canal e-mail au premier lot).
     *
     * - Armateur désigné → son e-mail de carnet, exclusivement (l'adresse libre
     *   est ignorée : on ne peut pas détourner une demande armateur ailleurs).
     * - Sans armateur → adresse libre, réservée au gérant (défense B1).
     *
     * @param  array<string, mixed>  $donnees
     */
    private function resoudreAdresse(array $donnees, User $auteur): string
    {
        $armateurId = $donnees['armateur_id'] ?? null;

        if ($armateurId !== null) {
            $email = Armateur::query()->whereKey($armateurId)->value('email');
            if (is_string($email) && $email !== '') {
                return $email;
            }
            throw ValidationException::withMessages([
                'destinataire_adresse' => "Cet armateur n'a pas d'e-mail de contact : renseignez-le dans sa fiche.",
            ]);
        }

        $adresse = trim((string) ($donnees['destinataire_adresse'] ?? ''));
        if ($adresse === '') {
            throw ValidationException::withMessages([
                'destinataire_adresse' => 'Renseignez un armateur ou une adresse de destinataire.',
            ]);
        }
        if ($auteur->role !== RoleUtilisateur::Gerant) {
            throw ValidationException::withMessages([
                'destinataire_adresse' => "Seul un gérant peut adresser un message hors du carnet d'armateurs.",
            ]);
        }

        return $adresse;
    }
}
