<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Messagerie\Contracts\ServiceOtp;
use App\Domains\Messagerie\Data\ContexteOtp;
use App\Domains\Portail\Models\InvitationPortail;
use App\Shared\Scopes\TenantScope;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Réclamation PUBLIQUE d'une invitation (le client ouvre le lien). Lit l'unique
 * ligne bornée par la RLS au token présenté (TenantScope levé, pas de bypass en
 * bloc), puis émet un OTP vers le destinataire. Réponse UNIFORME si l'invitation
 * est absente/expirée/consommée (anti-énumération). Ne révèle aucun contenu du
 * dossier (l'accès n'existe qu'après confirmation).
 */
final class ReclamerInvitation
{
    public function __construct(private readonly ServiceOtp $otp) {}

    /**
     * @return array{canal: string, destination_masquee: string, otp_expire_at: string}
     */
    public function executer(string $tokenBrut): array
    {
        $invitation = $this->trouverParToken($tokenBrut);

        if ($invitation === null || ! $invitation->estReclamable()) {
            throw new HttpException(404, 'Invitation introuvable ou expirée.');
        }

        $telephone = $invitation->destinataire_chiffre; // déchiffré par le cast
        $defi = $this->otp->emettre($telephone, new ContexteOtp($invitation->id));

        return [
            'canal' => $invitation->canal->value,
            'destination_masquee' => $this->masquer($telephone),
            'otp_expire_at' => $defi->expireAt->toIso8601String(),
        ];
    }

    private function trouverParToken(string $tokenBrut): ?InvitationPortail
    {
        return InvitationPortail::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('token_hash', hash('sha256', $tokenBrut))
            ->first();
    }

    private function masquer(string $valeur): string
    {
        $longueur = mb_strlen($valeur);

        if ($longueur <= 4) {
            return str_repeat('•', $longueur);
        }

        return mb_substr($valeur, 0, 2).str_repeat('•', $longueur - 4).mb_substr($valeur, -2);
    }
}
