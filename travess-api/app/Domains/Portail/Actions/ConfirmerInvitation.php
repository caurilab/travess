<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Messagerie\Contracts\ServiceOtp;
use App\Domains\Messagerie\Data\ContexteOtp;
use App\Domains\Messagerie\Enums\ResultatVerificationOtp;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use App\Domains\Portail\Models\AccesDossier;
use App\Domains\Portail\Models\InvitationPortail;
use App\Domains\Portail\Services\ProvisionnerCompteClient;
use App\Domains\Tenancy\Models\Client;
use App\Shared\Context\TenantContext;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Confirmation PUBLIQUE d'une invitation (OTP) : vérifie l'OTP, provisionne le
 * compte client + son workspace, active l'octroi acces_dossier, consomme
 * l'invitation (usage unique sous verrou), le tout audité. Émet un jeton pour un
 * accès immédiat à la vue limitée (BL + parcours).
 */
final class ConfirmerInvitation
{
    public function __construct(
        private readonly ServiceOtp $otp,
        private readonly ProvisionnerCompteClient $provision,
        private readonly TenantContext $tenant,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array{code_otp: string, nom?: string, mot_de_passe?: string}  $donnees
     * @return array{token: string, user_id: string}
     */
    public function executer(string $tokenBrut, array $donnees): array
    {
        $invitation = $this->trouverParToken($tokenBrut);

        if ($invitation === null || ! $invitation->estReclamable()) {
            throw new HttpException(404, 'Invitation introuvable ou expirée.');
        }

        $telephone = $invitation->destinataire_chiffre;

        $verif = $this->otp->verifier($telephone, $donnees['code_otp'], new ContexteOtp($invitation->id));

        if ($verif !== ResultatVerificationOtp::Valide) {
            throw new HttpException(422, match ($verif) {
                ResultatVerificationOtp::Verrouille => 'Trop de tentatives, réessayez plus tard.',
                ResultatVerificationOtp::Expire => 'Code expiré, demandez-en un nouveau.',
                default => 'Code invalide.',
            });
        }

        return DB::transaction(function () use ($invitation, $telephone, $donnees): array {
            // Anti double consommation concurrente : sérialise sur le token.
            DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$invitation->token_hash]);

            $nom = $donnees['nom'] ?? $this->nomClient($invitation) ?? 'Client';
            $user = $this->provision->executer($telephone, $nom, $donnees['mot_de_passe'] ?? null);

            // Activation de l'octroi + consommation (opérations système bornées).
            $this->tenant->runBypassed(function () use ($invitation, $user): void {
                AccesDossier::create([
                    'dossier_id' => $invitation->dossier_id,
                    'tenant_proprietaire_id' => $invitation->tenant_id,
                    'beneficiaire_user_id' => $user->id,
                    'beneficiaire_tenant_id' => $user->tenant_id,
                    'niveau' => $invitation->niveau->value,
                    'statut' => StatutAcces::Actif->value,
                    'origine' => OrigineAcces::InvitationTransitaire->value,
                    'created_by' => $invitation->created_by,
                ]);

                $consommees = InvitationPortail::query()
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereKey($invitation->id)
                    ->where('statut', StatutInvitation::Emise->value)
                    ->update([
                        'statut' => StatutInvitation::Consommee->value,
                        'consumed_by_user_id' => $user->id,
                        'consumed_at' => now(),
                    ]);

                // Course perdue (une confirmation concurrente a déjà consommé).
                if ($consommees === 0) {
                    throw new HttpException(409, 'Invitation déjà utilisée.');
                }
            });

            // Audit dans le tenant émetteur (transitaire), hors bypass.
            $this->tenant->pour($invitation->tenant_id, function () use ($invitation, $user): void {
                $this->auditeur->enregistrer(
                    'invitation_portail',
                    (string) $invitation->getKey(),
                    'invitation.consommee',
                    null,
                    ['beneficiaire_user_id' => $user->id],
                );
            });

            $token = $user->createToken('portail')->plainTextToken;

            return ['token' => $token, 'user_id' => (string) $user->getKey()];
        });
    }

    private function trouverParToken(string $tokenBrut): ?InvitationPortail
    {
        return InvitationPortail::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('token_hash', hash('sha256', $tokenBrut))
            ->first();
    }

    private function nomClient(InvitationPortail $invitation): ?string
    {
        return $this->tenant->runBypassed(fn (): ?string => Client::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($invitation->client_id)
            ->value('nom'));
    }
}
