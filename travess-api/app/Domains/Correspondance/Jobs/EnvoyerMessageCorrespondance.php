<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Jobs;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Enums\StatutEnvoi;
use App\Domains\Messagerie\Support\FabriqueExpediteur;
use App\Shared\Context\TenantContext;
use App\Shared\Jobs\JobTenantScoped;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Envoie un message de correspondance via l'infra Messagerie (principe n°9),
 * dans le contexte du tenant (JobTenantScoped).
 *
 * Idempotence anti double-envoi (audit C2) : le job prend un verrou d'état par
 * une transition ATOMIQUE en_file → en_cours (UPDATE conditionnel). Si zéro
 * ligne bascule, un autre worker (ou un rejeu) s'en charge déjà → on s'arrête
 * avant tout appel réseau. L'adresse en clair n'est jamais journalisée.
 */
final class EnvoyerMessageCorrespondance extends JobTenantScoped
{
    public function __construct(string $tenantId, public readonly string $messageId)
    {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        // Verrou d'envoi : seul le passage en_file → en_cours qui écrit
        // réellement une ligne autorise l'envoi (anti double-envoi / rejeu).
        $pris = Message::query()
            ->whereKey($this->messageId)
            ->where('statut', StatutMessage::EnFile->value)
            ->update(['statut' => StatutMessage::EnCours->value]);

        if ($pris === 0) {
            return; // déjà pris en charge, ou message supprimé
        }

        $message = Message::query()->find($this->messageId);
        if ($message === null) {
            return;
        }

        $destinataire = new Destinataire($message->canal, $message->destinataire_adresse);
        $sortant = new MessageSortant('correspondance_libre', [
            'objet' => $message->objet,
            'corps' => $message->corps,
        ]);

        $resultat = app(FabriqueExpediteur::class)->pour($message->canal)->envoyer($destinataire, $sortant);

        $statut = match ($resultat->statut) {
            StatutEnvoi::Accepte => StatutMessage::Envoye,
            StatutEnvoi::EnFile => StatutMessage::EnFile, // fournisseur différé (whatsapp/sms)
            StatutEnvoi::Echec => StatutMessage::Echec,
        };

        $avant = ['statut' => StatutMessage::EnCours->value];
        $message->forceFill([
            'statut' => $statut->value,
            'reference_externe' => $resultat->referenceExterne,
            'envoye_at' => $statut === StatutMessage::Envoye ? Carbon::now() : null,
            'erreur' => $statut === StatutMessage::Echec ? 'Envoi refusé par le fournisseur.' : null,
        ])->save();

        // Trace l'aboutissement réel de l'envoi (défense audit C3).
        app(Auditeur::class)->miseAJour($message, 'correspondance.aboutie', $avant);
    }

    /**
     * Filet de sécurité : le verrou en_cours est committé AVANT l'appel réseau,
     * donc une exception d'envoi (SMTP indisponible…) laisserait le message figé
     * en « en_cours » — sans erreur, non rejouable (le verrou renvoie 0 au rejeu).
     * On rebascule en « échec » pour que l'agent voie l'état et puisse recomposer.
     * S'exécute hors contexte tenant : on le rétablit explicitement.
     */
    public function failed(Throwable $e): void
    {
        app(TenantContext::class)->pour($this->tenantId, function (): void {
            Message::query()
                ->whereKey($this->messageId)
                ->where('statut', StatutMessage::EnCours->value)
                ->update([
                    'statut' => StatutMessage::Echec->value,
                    'erreur' => "Échec technique de l'envoi. Réessayez.",
                ]);
        });
    }
}
