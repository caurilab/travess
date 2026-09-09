<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Adapters;

use App\Domains\Messagerie\Contracts\ServiceOtp;
use App\Domains\Messagerie\Data\ContexteOtp;
use App\Domains\Messagerie\Data\DefiOtp;
use App\Domains\Messagerie\Enums\ResultatVerificationOtp;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * OTP factice : code déterministe (config messagerie.otp.code_factice), sans
 * réseau, MAIS cycle de vie complet exercé (expiration via TTL du cache, plafond
 * de tentatives, verrouillage, liaison à l'invitation). L'état vit dans le cache
 * pour survivre entre la requête de réclamation et celle de confirmation.
 */
final class ServiceOtpFactice implements ServiceOtp
{
    public function __construct(private readonly Cache $cache) {}

    public function emettre(string $telephone, ContexteOtp $contexte): DefiOtp
    {
        $ttl = (int) config('messagerie.otp.ttl_secondes');
        $expire = Carbon::now()->addSeconds($ttl);

        $this->cache->put($this->cle($contexte), [
            'code' => (string) config('messagerie.otp.code_factice', '123456'),
            'telephone' => $telephone,
            'tentatives' => 0,
            'expire' => $expire->getTimestamp(),
        ], $expire);

        return new DefiOtp($contexte->invitationId, $expire);
    }

    public function verifier(string $telephone, string $code, ContexteOtp $contexte): ResultatVerificationOtp
    {
        /** @var array{code: string, telephone: string, tentatives: int, expire: int}|null $etat */
        $etat = $this->cache->get($this->cle($contexte));

        if ($etat === null) {
            return ResultatVerificationOtp::Expire;
        }

        if ($etat['tentatives'] >= (int) config('messagerie.otp.tentatives_max')) {
            return ResultatVerificationOtp::Verrouille;
        }

        $bon = hash_equals($etat['code'], $code) && hash_equals($etat['telephone'], $telephone);

        if (! $bon) {
            $etat['tentatives']++;
            $this->cache->put($this->cle($contexte), $etat, Carbon::createFromTimestamp($etat['expire']));

            return $etat['tentatives'] >= (int) config('messagerie.otp.tentatives_max')
                ? ResultatVerificationOtp::Verrouille
                : ResultatVerificationOtp::Invalide;
        }

        // Usage unique : le défi validé est consommé.
        $this->cache->forget($this->cle($contexte));

        return ResultatVerificationOtp::Valide;
    }

    private function cle(ContexteOtp $contexte): string
    {
        return 'otp:factice:'.$contexte->invitationId;
    }
}
