<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Adapters;

use App\Domains\Tracking\Contracts\FournisseurTracking;
use App\Domains\Tracking\Data\NavireData;
use App\Domains\Tracking\Data\StatsQuotaData;
use App\Domains\Tracking\Data\SuiviConteneurData;
use App\Domains\Tracking\Enums\PhaseConteneur;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fournisseur JSONCargo RÉEL (docs/09), isolé derrière FournisseurTracking
 * (principe n°9). Authentification par header `x-api-key` ; base URL en HTTPS
 * (confirmé). La clé mutualisée vit en .env, n'est JAMAIS journalisée ni
 * recopiée dans un snapshot (on ne stocke que la charge métier renvoyée).
 */
final class AdaptateurJsonCargo implements FournisseurTracking
{
    public function conteneursDepuisBl(string $numeroBl, string $armateurApi): array
    {
        // E2 — numéros de conteneur d'un BL (inversion de saisie). Slash final :
        // sans lui l'API redirige (301).
        $d = $this->donnees(
            $this->client()->get("containers/bol/{$numeroBl}/", ['shipping_line' => $armateurApi])->throw()->json()
        );

        $numeros = $d['associated_container_numbers'] ?? $d;

        return array_values(array_filter(array_map('strval', is_array($numeros) ? $numeros : [])));
    }

    public function suivreConteneur(string $numero, string $armateurApi): SuiviConteneurData
    {
        // E1 — détails d'un conteneur (slash final obligatoire).
        $c = $this->donnees(
            $this->client()->get("containers/{$numero}/", ['shipping_line' => $armateurApi])->throw()->json()
        );

        if ($c === []) {
            throw new RuntimeException('Réponse JSONCargo vide pour le conteneur.');
        }

        $statutBrut = (string) ($c['container_status'] ?? '');

        return new SuiviConteneurData(
            phase: $this->mapperPhase($statutBrut),
            statutBrut: $statutBrut,
            emplacement: $this->texte($c['last_location'] ?? $c['discharging_port'] ?? null),
            etaDestination: $this->date($c['eta_final_destination'] ?? $c['eta_next_destination'] ?? null),
            // E1 ne fournit pas l'IMO ; le nom sert à l'affichage, l'IMO
            // (principe n°7) est résolu à part via resoudreNavireImo (E6) si besoin.
            navireNom: $this->texte($c['current_vessel_name'] ?? $c['last_vessel_name'] ?? null),
            navireImo: null,
            snapshotBrut: $c,
            capturedAt: CarbonImmutable::now(),
        );
    }

    public function resoudreNavireImo(string $imoOuNom): NavireData
    {
        // E6 — vessel finder. Peut renvoyer plusieurs navires de même nom : on
        // s'appuie sur l'IMO (principe n°7). On prend la 1re fiche exploitable.
        $fiche = $this->premiereFiche($this->donnees($this->client()->get("vessels/finder/{$imoOuNom}/")->throw()->json()));

        $imo = $this->texte($fiche['imo'] ?? null);
        if ($imo === null) {
            throw new RuntimeException("Navire sans IMO exploitable pour « {$imoOuNom} ».");
        }

        return new NavireData(
            imo: $imo,
            mmsi: $this->texte($fiche['mmsi'] ?? null),
            nom: $this->texte($fiche['name'] ?? $fiche['name_ais'] ?? null),
        );
    }

    public function statsQuota(): StatsQuotaData
    {
        // E10 — statistiques de la clé (pilotage du plafond).
        $s = $this->donnees($this->client()->get('api_key/stats')->throw()->json());

        return new StatsQuotaData(
            plan: (string) ($s['plan'] ?? 'inconnu'),
            requetesTotal: (int) ($s['requests_total'] ?? 0),
            requetesFaites: (int) ($s['requests_made'] ?? 0),
            requetesDisponibles: (int) ($s['requests_available'] ?? 0),
        );
    }

    private function client(): PendingRequest
    {
        $base = rtrim((string) config('tracking.base_url'), '/');
        $cle = (string) config('tracking.api_key');

        if ($base === '' || $cle === '') {
            throw new RuntimeException('JSONCargo non configuré : renseignez JSONCARGO_BASE_URL et JSONCARGO_API_KEY.');
        }
        if (! Str::startsWith($base, 'https://')) {
            // Ne jamais envoyer la clé en clair (docs/09 §1).
            throw new RuntimeException('JSONCARGO_BASE_URL doit être en HTTPS avant tout envoi de la clé.');
        }

        return Http::baseUrl($base.'/')
            ->withHeaders(['x-api-key' => $cle])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 250);
    }

    /**
     * Mappe le `container_status` brut (vocabulaire fournisseur, docs/09 §4) vers
     * une phase. Heuristique par mots-clés + surcharge config
     * (tracking.mapping_phases). Valeur inconnue → EnMer (statut « à traiter »,
     * défaut prudent) + journalisation pour compléter la table.
     */
    private function mapperPhase(string $statutBrut): PhaseConteneur
    {
        $cle = Str::of($statutBrut)->lower()->trim()->value();

        /** @var array<string, string> $surcharge */
        $surcharge = config('tracking.mapping_phases', []);
        if (isset($surcharge[$cle]) && ($p = PhaseConteneur::tryFrom($surcharge[$cle])) !== null) {
            return $p;
        }

        $phase = match (true) {
            $cle === '' => PhaseConteneur::EnMer,
            Str::contains($cle, ['empty return', 'returned', 'devanning', 'rendu']) => PhaseConteneur::Rendu,
            Str::contains($cle, ['delivered', 'livr']) => PhaseConteneur::Livre,
            Str::contains($cle, ['gate out', 'picked up', 'departed terminal', 'enlev']) => PhaseConteneur::Enleve,
            Str::contains($cle, ['discharged', 'unloaded', 'arrived', 'at port', 'gate in', 'décharg']) => PhaseConteneur::Decharge,
            Str::contains($cle, ['approaching', 'near', 'anchorage', 'approche']) => PhaseConteneur::Approche,
            Str::contains($cle, ['on vessel', 'in transit', 'sailing', 'on water', 'loaded', 'mer']) => PhaseConteneur::EnMer,
            default => PhaseConteneur::EnMer,
        };

        if ($phase === PhaseConteneur::EnMer && ! Str::contains($cle, ['on vessel', 'in transit', 'sailing', 'on water', 'loaded', 'mer']) && $cle !== '') {
            // Statut non reconnu : on journalise pour enrichir la table (docs/09 §4).
            Log::info('Statut conteneur JSONCargo non mappé', ['container_status' => $statutBrut]);
        }

        return $phase;
    }

    /**
     * Déballe l'enveloppe { "data": ... } de JSONCargo. Renvoie [] si vide.
     *
     * @return array<int|string, mixed>
     */
    private function donnees(mixed $reponse): array
    {
        if (! is_array($reponse)) {
            return [];
        }

        $contenu = $reponse['data'] ?? $reponse;

        return is_array($contenu) ? $contenu : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function premiereFiche(mixed $reponse): array
    {
        if (is_array($reponse) && isset($reponse[0]) && is_array($reponse[0])) {
            return $reponse[0];
        }

        return is_array($reponse) ? $reponse : [];
    }

    private function texte(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return is_scalar($valeur) ? (string) $valeur : null;
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        $texte = $this->texte($valeur);
        if ($texte === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($texte);
        } catch (\Throwable) {
            return null;
        }
    }
}
