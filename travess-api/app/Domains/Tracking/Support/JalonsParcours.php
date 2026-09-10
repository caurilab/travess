<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Support;

use Carbon\CarbonImmutable;

/**
 * Dérive une frise datée (jalons) du parcours d'un conteneur à partir du
 * snapshot brut du fournisseur (principe n°9 : le format fournisseur ne fuit
 * pas plus loin qu'ici). JSONCargo n'expose pas de journal d'événements ligne à
 * ligne, mais quatre points de route datés : origine, dernier lieu (position
 * courante), prochain lieu, destination finale. On les projette en une liste
 * ordonnée, prête pour un affichage « à la MSC » côté web.
 *
 * Chaque jalon : code, libelle, lieu, terminal, date (ISO 8601 ou null),
 * date_estimee (true = ETA prévue, false = mouvement constaté), navire, etat
 * (fait | actuel | prevu). Le helper est pur : aucune I/O, entièrement testable.
 */
final class JalonsParcours
{
    /** Priorité de conservation en cas de même lieu (plus haut = gardé). */
    private const PRIORITE = [
        'depart' => 1,
        'escale' => 2,
        'destination' => 3,
        'position' => 4,
    ];


    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    public static function depuis(array $snapshot): array
    {
        $navire = self::texte($snapshot['current_vessel_name'] ?? $snapshot['last_vessel_name'] ?? null);

        // Points candidats, dans l'ordre chronologique du parcours.
        $candidats = [
            [
                'code' => 'depart',
                'libelle' => 'Départ',
                'lieu' => self::texte($snapshot['shipped_from'] ?? $snapshot['loading_port'] ?? null),
                'terminal' => self::texte($snapshot['shipped_from_terminal'] ?? null),
                'date' => self::date($snapshot['atd_origin'] ?? null),
                'date_estimee' => false,
                'navire' => $navire,
            ],
            [
                'code' => 'position',
                'libelle' => 'Position actuelle',
                'lieu' => self::texte($snapshot['last_location'] ?? null),
                'terminal' => self::texte($snapshot['last_location_terminal'] ?? null),
                'date' => self::date($snapshot['last_movement_timestamp'] ?? $snapshot['atd_last_location'] ?? null),
                'date_estimee' => false,
                'navire' => $navire,
                'detail' => self::texte($snapshot['container_status'] ?? null),
            ],
            [
                'code' => 'escale',
                'libelle' => 'Prochaine escale',
                'lieu' => self::texte($snapshot['next_location'] ?? null),
                'terminal' => self::texte($snapshot['next_location_terminal'] ?? null),
                'date' => self::date($snapshot['eta_next_destination'] ?? null),
                'date_estimee' => true,
                'navire' => null,
            ],
            [
                'code' => 'destination',
                'libelle' => 'Destination finale',
                'lieu' => self::texte($snapshot['shipped_to'] ?? $snapshot['discharging_port'] ?? null),
                'terminal' => self::texte($snapshot['shipped_to_terminal'] ?? null),
                'date' => self::date($snapshot['eta_final_destination'] ?? null),
                'date_estimee' => true,
                'navire' => null,
            ],
        ];

        // On ne garde que les points renseignés (un lieu au minimum). Quand deux
        // jalons consécutifs pointent le même lieu (départ == position au
        // chargement, escale == destination à l'arrivée), on conserve le plus
        // pertinent selon PRIORITE : la position réelle prime toujours, puis la
        // destination finale sur une simple escale.
        $jalons = [];
        foreach ($candidats as $c) {
            if ($c['lieu'] === null) {
                continue;
            }
            $dernier = $jalons === [] ? null : $jalons[array_key_last($jalons)];
            if ($dernier !== null && self::normaliser($dernier['lieu']) === self::normaliser($c['lieu'])) {
                if (self::PRIORITE[$c['code']] > self::PRIORITE[$dernier['code']]) {
                    $jalons[array_key_last($jalons)] = $c;
                }

                continue;
            }
            $jalons[] = $c;
        }

        return self::marquerEtats($jalons);
    }

    /**
     * Marque chaque jalon : ceux avant la position courante sont « fait », le
     * jalon « position » est « actuel », les suivants sont « prevu ». Sans jalon
     * position (ex. tout début), le premier point renseigné porte l'état actuel.
     *
     * @param  list<array<string, mixed>>  $jalons
     * @return list<array<string, mixed>>
     */
    private static function marquerEtats(array $jalons): array
    {
        $indexActuel = null;
        foreach ($jalons as $i => $j) {
            if ($j['code'] === 'position') {
                $indexActuel = $i;
                break;
            }
        }
        if ($indexActuel === null && $jalons !== []) {
            $indexActuel = 0;
        }

        foreach ($jalons as $i => &$j) {
            $j['etat'] = match (true) {
                $indexActuel === null => 'prevu',
                $i < $indexActuel => 'fait',
                $i === $indexActuel => 'actuel',
                default => 'prevu',
            };
        }
        unset($j);

        return $jalons;
    }

    private static function texte(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return is_scalar($valeur) ? trim((string) $valeur) : null;
    }

    private static function date(mixed $valeur): ?string
    {
        $texte = self::texte($valeur);
        if ($texte === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($texte)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normaliser(string $lieu): string
    {
        return mb_strtolower(trim($lieu));
    }
}
