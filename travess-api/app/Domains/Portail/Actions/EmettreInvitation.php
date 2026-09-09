<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use App\Domains\Portail\Jobs\EnvoyerInvitation;
use App\Domains\Portail\Models\InvitationPortail;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Émet une invitation d'onboarding (transitaire → client) : crée l'invitation
 * (token haché, jamais stocké en clair), puis envoie le lien signé EN FILE
 * (principe n°4). Tenant-scopée et auditée. Aucun compte n'est créé ici :
 * l'invitation « emise » porte l'état d'attente jusqu'à la confirmation.
 */
final class EmettreInvitation
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{canal: string, destinataire: string, niveau?: string, client_id?: string}  $donnees
     * @return array{invitation: InvitationPortail, lien: string}
     */
    public function executer(Dossier $dossier, array $donnees): array
    {
        $tokenBrut = $this->genererToken();
        $expire = Carbon::now()->addHours((int) config('messagerie.invitation.ttl_heures'));

        return DB::transaction(function () use ($dossier, $donnees, $tokenBrut, $expire): array {
            $invitation = InvitationPortail::create([
                'token_hash' => hash('sha256', $tokenBrut),
                'dossier_id' => $dossier->id,
                'client_id' => $donnees['client_id'] ?? $dossier->client_id,
                'canal' => $donnees['canal'],
                'destinataire_chiffre' => $donnees['destinataire'],
                'niveau' => $donnees['niveau'] ?? NiveauAcces::Limite->value,
                'statut' => StatutInvitation::Emise->value,
                'expire_at' => $expire,
                'created_by' => Auth::id(),
            ]);

            $this->auditeur->creation($invitation, 'invitation.emise');

            // Lien signé (HMAC + expiry) portant le token brut dans le CHEMIN.
            $lien = URL::temporarySignedRoute('portail.invitation.montrer', $expire, ['token' => $tokenBrut]);

            EnvoyerInvitation::dispatch(
                $this->tenant->idOrFail(),
                $donnees['canal'],
                $donnees['destinataire'],
                $lien,
                $dossier->reference,
            )->afterCommit();

            return ['invitation' => $invitation, 'lien' => $lien];
        });
    }

    private function genererToken(): string
    {
        $octets = (int) config('messagerie.invitation.longueur_token_octets');

        return rtrim(strtr(base64_encode(random_bytes($octets)), '+/', '-_'), '=');
    }
}
