<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Adapters;

use App\Domains\Conteneurs\Support\Iso6346;
use App\Domains\Tracking\Contracts\FournisseurTracking;
use App\Domains\Tracking\Data\NavireData;
use App\Domains\Tracking\Data\StatsQuotaData;
use App\Domains\Tracking\Data\SuiviConteneurData;
use App\Domains\Tracking\Enums\PhaseConteneur;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Fournisseur de tracking FACTICE : déterministe, sans réseau ni clé. Permet de
 * tester tout le pipeline (dev + suite) sans JSONCargo. Lié en singleton pour
 * que test et code partagent le même état simulé (quota, journaux).
 *
 * Cycle par défaut : la phase d'un conteneur avance avec le temps (via son
 * ancrage déterministe) — rappeler le même conteneur plus tard le fait
 * progresser en mer → approche → déchargé → enlevé → livré → rendu. Les tests
 * peuvent forcer un résultat exact via simuler() ou pousser le quota.
 */
final class AdaptateurTrackingFactice implements FournisseurTracking
{
    private ?SuiviConteneurData $resultatSimule = null;

    private bool $echouera = false;

    /** Fraction du quota simulée comme déjà consommée (0.0 → 1.0). */
    private float $quotaConsomme = 0.04;

    public function simuler(?SuiviConteneurData $suivi): self
    {
        $this->resultatSimule = $suivi;

        return $this;
    }

    public function echouera(bool $echouera = true): self
    {
        $this->echouera = $echouera;

        return $this;
    }

    /** Positionne le quota consommé simulé (pour exercer alerte 75 % / coupure 90 %). */
    public function poserQuotaConsomme(float $fraction): self
    {
        $this->quotaConsomme = max(0.0, min(1.0, $fraction));

        return $this;
    }

    public function conteneursDepuisBl(string $numeroBl, string $armateurApi): array
    {
        $graine = crc32($numeroBl.$armateurApi);
        $nombre = ($graine % 3) + 1; // 1 à 3 conteneurs
        $numeros = [];
        for ($i = 0; $i < $nombre; $i++) {
            $numeros[] = $this->numeroValide($graine + $i);
        }

        return $numeros;
    }

    public function suivreConteneur(string $numero, string $armateurApi): SuiviConteneurData
    {
        if ($this->echouera) {
            throw new RuntimeException('Tracking factice en échec (simulé).');
        }
        if ($this->resultatSimule !== null) {
            return $this->resultatSimule;
        }

        $maintenant = CarbonImmutable::now();
        $position = (crc32($numero) + (int) CarbonImmutable::create(2026, 1, 1)->diffInDays($maintenant)) % 26;
        $phase = $this->phasePourPosition($position);

        // ETA : dans le futur en mer, proche à l'approche, passée après déchargement.
        $joursAvantArrivee = max(-20, 12 - $position);
        $eta = $maintenant->addDays($joursAvantArrivee);

        $navire = $this->resoudreNavireImo('Navire '.strtoupper(substr($armateurApi, 0, 3)));

        return new SuiviConteneurData(
            phase: $phase,
            statutBrut: 'FACTICE_'.$phase->value,
            emplacement: $this->emplacementPourPhase($phase),
            etaDestination: $eta,
            navireNom: $navire->nom,
            navireImo: $navire->imo,
            snapshotBrut: ['source' => 'factice', 'position' => $position, 'phase' => $phase->value],
            capturedAt: $maintenant,
        );
    }

    public function resoudreNavireImo(string $imoOuNom): NavireData
    {
        // IMO factice stable à 7 chiffres dérivé de l'entrée (principe n°7 : on
        // identifie par IMO, jamais par le nom seul).
        $imo = (string) (1000000 + (crc32($imoOuNom) % 9000000));

        return new NavireData(imo: $imo, mmsi: (string) (200000000 + (crc32($imoOuNom) % 99999999)), nom: $imoOuNom);
    }

    public function statsQuota(): StatsQuotaData
    {
        $total = 2500;
        $faites = (int) round($total * $this->quotaConsomme);

        return new StatsQuotaData(
            plan: 'Navigator',
            requetesTotal: $total,
            requetesFaites: $faites,
            requetesDisponibles: max(0, $total - $faites),
        );
    }

    private function phasePourPosition(int $position): PhaseConteneur
    {
        return match (true) {
            $position < 10 => PhaseConteneur::EnMer,
            $position < 14 => PhaseConteneur::Approche,
            $position < 16 => PhaseConteneur::Decharge,
            $position < 20 => PhaseConteneur::Enleve,
            $position < 24 => PhaseConteneur::Livre,
            default => PhaseConteneur::Rendu,
        };
    }

    private function emplacementPourPhase(PhaseConteneur $phase): string
    {
        return match ($phase) {
            PhaseConteneur::EnMer => 'En mer',
            PhaseConteneur::Approche => 'Approche du port',
            PhaseConteneur::Decharge => 'Port de déchargement',
            PhaseConteneur::Enleve => 'Terminal — enlevé',
            PhaseConteneur::Livre => 'Livré au client',
            PhaseConteneur::Rendu => 'Conteneur rendu',
        };
    }

    private function numeroValide(int $graine): string
    {
        $prefixe = 'TRVU'.str_pad((string) ($graine % 1000000), 6, '0', STR_PAD_LEFT);
        $prefixe = Iso6346::normaliser($prefixe);

        return $prefixe.Iso6346::chiffreDeControle($prefixe);
    }
}
