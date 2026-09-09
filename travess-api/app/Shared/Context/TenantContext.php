<?php

declare(strict_types=1);

namespace App\Shared\Context;

use App\Shared\Exceptions\TenantContextMissingException;
use Illuminate\Support\Facades\DB;

/**
 * Porteur du tenant courant pour la durée d'une requête (ou d'un traitement).
 *
 * Enregistré en binding « scoped » : une instance par cycle applicatif (une
 * requête HTTP), réinitialisée entre deux requêtes sous runtime persistant.
 * Le tenant_id y est déposé par le middleware EnsureTenantContext à partir de
 * l'utilisateur authentifié — jamais d'un paramètre client.
 *
 * Double effet, maintenu cohérent :
 *  - en mémoire, pour le scoping Eloquent (TenantScope) ;
 *  - dans le GUC de session PostgreSQL « app.tenant_id », pour la Row-Level
 *    Security (défense en profondeur base, cf. RlsTenant).
 */
final class TenantContext
{
    private ?string $tenantId = null;

    /**
     * Vrai tant qu'une opération système explicitement non scopée est en cours
     * (amorçage d'authentification, super-admin éditeur, maintenance).
     */
    private bool $bypassed = false;

    /**
     * Bénéficiaire d'accès partagé courant (portail). Alimente le GUC
     * « app.portail_user_id » pour la RLS de partage (ADR-013). Posé par le
     * middleware portail depuis l'utilisateur authentifié, jamais par le client.
     */
    private ?string $portailUserId = null;

    /**
     * Empreinte (hash) du token d'invitation présenté sur le chemin de
     * réclamation PUBLIC. Alimente le GUC « app.invitation_token_hash » pour la
     * RLS de l'invitation (lecture bornée à une ligne). Posé par le seul
     * middleware public de réclamation, jamais par une entrée client arbitraire.
     */
    private ?string $invitationTokenHash = null;

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function portailUserId(): ?string
    {
        return $this->portailUserId;
    }

    public function invitationTokenHash(): ?string
    {
        return $this->invitationTokenHash;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Renvoie le tenant courant ou échoue si aucun n'est établi (fail-closed).
     */
    public function idOrFail(): string
    {
        if ($this->tenantId === null) {
            throw TenantContextMissingException::forOperation();
        }

        return $this->tenantId;
    }

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->definirGuc('app.tenant_id', $tenantId);
    }

    public function forget(): void
    {
        $this->tenantId = null;
        $this->definirGuc('app.tenant_id', '');

        // On ne laisse jamais traîner un bénéficiaire de partage au-delà de la
        // requête : sous runtime persistant, un app.portail_user_id résiduel
        // rouvrirait des dossiers partagés à la requête suivante.
        $this->forgetPortailUser();
        $this->forgetInvitationToken();
    }

    /**
     * Positionne l'empreinte du token d'invitation présenté (réclamation
     * publique). Réservé au middleware de réclamation : la valeur est le hash du
     * token porté par l'URL signée, jamais un app.invitation_token_hash fourni.
     */
    public function setInvitationToken(string $tokenHash): void
    {
        $this->invitationTokenHash = $tokenHash;
        $this->definirGuc('app.invitation_token_hash', $tokenHash);
    }

    public function forgetInvitationToken(): void
    {
        $this->invitationTokenHash = null;
        $this->definirGuc('app.invitation_token_hash', '');
    }

    /**
     * Remet TOUS les GUC de session à vide (fail-closed). À poser en tête des
     * chemins publics (sans middleware tenant) : sous runtime persistant/pooler,
     * un app.tenant_id / app.bypass_rls / app.portail_user_id résiduel d'une
     * requête précédente élargirait la RLS. On ne suppose jamais un état propre.
     */
    public function reinitialiser(): void
    {
        $this->tenantId = null;
        $this->portailUserId = null;
        $this->invitationTokenHash = null;
        $this->bypassed = false;

        $this->definirGuc('app.tenant_id', '');
        $this->definirGuc('app.portail_user_id', '');
        $this->definirGuc('app.invitation_token_hash', '');
        $this->definirGuc('app.bypass_rls', '');
    }

    /**
     * Positionne le bénéficiaire de partage courant (portail). Réservé au
     * middleware portail : la valeur vient de l'utilisateur authentifié.
     */
    public function setPortailUser(string $userId): void
    {
        $this->portailUserId = $userId;
        $this->definirGuc('app.portail_user_id', $userId);
    }

    public function forgetPortailUser(): void
    {
        $this->portailUserId = null;
        $this->definirGuc('app.portail_user_id', '');
    }

    /**
     * Exécute un traitement dans le contexte d'accès partagé d'un bénéficiaire,
     * puis restaure l'état précédent (imbrication sûre). Point d'entrée du
     * middleware portail et des tests d'étanchéité de partage.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function sousPortail(string $portailUserId, \Closure $callback): mixed
    {
        $precedent = $this->portailUserId;
        $this->setPortailUser($portailUserId);

        try {
            return $callback();
        } finally {
            if ($precedent === null) {
                $this->forgetPortailUser();
            } else {
                $this->setPortailUser($precedent);
            }
        }
    }

    /**
     * Exécute un traitement dans le contexte d'un tenant donné, puis restaure
     * l'état précédent (imbrication sûre). C'est le point d'entrée des jobs,
     * commandes et du scheduler : ils DOIVENT établir le contexte via cette
     * méthode avant de toucher un modèle scopé, sinon le fail-closed s'applique.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function pour(string $tenantId, \Closure $callback): mixed
    {
        $precedent = $this->tenantId;
        $this->set($tenantId);

        try {
            return $callback();
        } finally {
            if ($precedent === null) {
                $this->forget();
            } else {
                $this->set($precedent);
            }
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Exécute un traitement sans scoping tenant (échappatoire système explicite).
     *
     * Lève le scoping Eloquent ET la RLS (GUC app.bypass_rls) pour la durée du
     * callback. Réservé aux opérations qui, par nature, précèdent ou transcendent
     * le tenant : résolution de l'utilisateur au login, supervision éditeur tracée.
     * Tout usage doit être justifié et audité — jamais un raccourci pour
     * contourner l'isolation dans un contexte métier.
     */
    public function runBypassed(callable $callback): mixed
    {
        $precedent = $this->bypassed;
        $this->bypassed = true;
        $this->definirGuc('app.bypass_rls', 'on');

        try {
            return $callback();
        } finally {
            $this->bypassed = $precedent;
            $this->definirGuc('app.bypass_rls', $precedent ? 'on' : '');
        }
    }

    /**
     * Positionne un GUC de session PostgreSQL (persistant sur la connexion,
     * hors transaction). Sans effet sur d'autres SGBD (tests unitaires purs).
     */
    private function definirGuc(string $nom, string $valeur): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne('select set_config(?, ?, false)', [$nom, $valeur]);
    }
}
