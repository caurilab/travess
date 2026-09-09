<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Adapters;

use App\Domains\Messagerie\Contracts\ServiceOtp;
use App\Domains\Messagerie\Data\ContexteOtp;
use App\Domains\Messagerie\Data\DefiOtp;
use App\Domains\Messagerie\Enums\ResultatVerificationOtp;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * OTP factice : code déterministe (config), sans réseau, MAIS cycle de vie
 * complet et RÉSISTANT À L'ABUS (audit 7.2a, M1) :
 *  - tentatives comptées de façon PERSISTANTE (au-delà du TTL du code) et NON
 *    réinitialisées à la réémission ;
 *  - verrou effectif de `verrou_secondes` une fois le plafond atteint ;
 *  - plafond d'émissions par numéro/heure et par invitation (anti SMS-pumping).
 *
 * L'état vit dans le cache pour survivre entre les requêtes publiques.
 */
final class ServiceOtpFactice implements ServiceOtp
{
    public function __construct(private readonly Cache $cache) {}

    public function emettre(string $telephone, ContexteOtp $contexte): DefiOtp
    {
        if ($this->cache->get($this->cleVerrou($contexte)) !== null) {
            throw new HttpException(429, 'Trop de tentatives, réessayez plus tard.');
        }

        $this->plafonner(
            $this->cleEmissionsInvitation($contexte),
            (int) config('messagerie.otp.emissions_max_par_invitation'),
            (int) config('messagerie.otp.verrou_secondes'),
        );
        $this->plafonner(
            $this->cleEmissionsNumero($telephone),
            (int) config('messagerie.otp.envois_max_par_numero_heure'),
            3600,
        );

        $ttl = (int) config('messagerie.otp.ttl_secondes');
        $expire = Carbon::now()->addSeconds($ttl);

        // On (ré)émet le code sans jamais remettre le compteur de tentatives à 0.
        $this->cache->put($this->cleCode($contexte), [
            'code' => (string) config('messagerie.otp.code_factice', '123456'),
            'telephone' => $telephone,
        ], $expire);

        return new DefiOtp($contexte->invitationId, $expire);
    }

    public function verifier(string $telephone, string $code, ContexteOtp $contexte): ResultatVerificationOtp
    {
        if ($this->cache->get($this->cleVerrou($contexte)) !== null) {
            return ResultatVerificationOtp::Verrouille;
        }

        /** @var array{code: string, telephone: string}|null $defi */
        $defi = $this->cache->get($this->cleCode($contexte));

        if ($defi === null) {
            return ResultatVerificationOtp::Expire;
        }

        if (hash_equals($defi['code'], $code) && hash_equals($defi['telephone'], $telephone)) {
            $this->cache->forget($this->cleCode($contexte));
            $this->cache->forget($this->cleTentatives($contexte));

            return ResultatVerificationOtp::Valide;
        }

        $tentatives = (int) $this->cache->get($this->cleTentatives($contexte), 0) + 1;
        $max = (int) config('messagerie.otp.tentatives_max');
        $verrou = (int) config('messagerie.otp.verrou_secondes');

        $this->cache->put($this->cleTentatives($contexte), $tentatives, $verrou);

        if ($tentatives >= $max) {
            $this->cache->put($this->cleVerrou($contexte), true, $verrou);
            $this->cache->forget($this->cleCode($contexte));

            return ResultatVerificationOtp::Verrouille;
        }

        return ResultatVerificationOtp::Invalide;
    }

    private function plafonner(string $cle, int $max, int $ttl): void
    {
        $compteur = (int) $this->cache->get($cle, 0);

        if ($compteur >= $max) {
            throw new HttpException(429, 'Plafond d’envois OTP atteint, réessayez plus tard.');
        }

        // add() pose le TTL au premier passage ; increment() garde la fenêtre.
        $this->cache->add($cle, 0, $ttl);
        $this->cache->increment($cle);
    }

    private function cleCode(ContexteOtp $c): string
    {
        return 'otp:code:'.$c->invitationId;
    }

    private function cleTentatives(ContexteOtp $c): string
    {
        return 'otp:tentatives:'.$c->invitationId;
    }

    private function cleVerrou(ContexteOtp $c): string
    {
        return 'otp:verrou:'.$c->invitationId;
    }

    private function cleEmissionsInvitation(ContexteOtp $c): string
    {
        return 'otp:emissions:'.$c->invitationId;
    }

    private function cleEmissionsNumero(string $telephone): string
    {
        return 'otp:numero:'.hash('sha256', $telephone);
    }
}
