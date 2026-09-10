<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Tracking\Contracts\FournisseurTracking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Plafond de sécurité du quota MUTUALISÉ (docs/08 §3) : au-delà d'un seuil, on
 * coupe le polling automatique et on bascule sur IMAP/manuel, pour ne jamais
 * cramer le quota ni tomber en dépassement.
 *
 * Le quota est global (un seul abonnement) : cache et alerte utilisent des clés
 * globales, jamais scopées tenant. Distinct de la consommation par tenant
 * (DecompteConsommationTracking), qui sert la facturation.
 */
final class GardeQuotaTracking
{
    public function __construct(private readonly FournisseurTracking $fournisseur) {}

    /**
     * Autorise-t-on un appel fournisseur maintenant ? Faux dès ~90 % du quota
     * consommé (bascule IMAP/manuel). Émet au passage l'alerte interne à 75 %.
     */
    public function autoriseAppel(): bool
    {
        $pourcentage = $this->pourcentageConsomme();

        if ($pourcentage >= (float) config('tracking.plafond_alerte', 0.75)) {
            $this->alerterInterne($pourcentage);
        }

        return $pourcentage < (float) config('tracking.plafond_coupure', 0.90);
    }

    public function pourcentageConsomme(): float
    {
        // L'appel /stats est lui-même facturé : on le met en cache par run
        // (clé globale). TTL 0 en test pour refléter immédiatement le quota simulé.
        $ttl = (int) config('tracking.cache_stats_ttl', 300);

        $lire = fn (): float => $this->fournisseur->statsQuota()->pourcentageConsomme();

        if ($ttl <= 0) {
            return $lire();
        }

        return (float) Cache::remember('tracking.stats.pourcentage', $ttl, $lire);
    }

    /**
     * Alerte interne (ops) une seule fois par mois, avant la coupure. Ce n'est
     * pas une alerte métier de tenant : c'est un signal à l'équipe pour ajuster
     * le plan avant saturation.
     */
    private function alerterInterne(float $pourcentage): void
    {
        $cle = 'tracking.alerte_quota.'.now()->format('Y-m');

        if (Cache::add($cle, true, now()->addMonth())) {
            Log::warning('Quota tracking élevé', ['pourcentage' => round($pourcentage * 100, 1)]);
        }
    }
}
