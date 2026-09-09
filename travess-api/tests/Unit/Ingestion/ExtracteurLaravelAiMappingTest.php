<?php

declare(strict_types=1);

namespace Tests\Unit\Ingestion;

use App\Domains\Ingestion\Adapters\ExtracteurLaravelAi;
use App\Domains\Ingestion\Data\DocumentAExtraire;
use App\Domains\Ingestion\Data\SchemaExtraction;
use App\Domains\Ingestion\Schemas\RegistreSchemas;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Laravel\Ai\StructuredAnonymousAgent;
use RuntimeException;
use Tests\TestCase;

/**
 * Teste la logique portable de l'adaptateur réel — le mapping sortie
 * structurée → ChampExtrait — SANS clé API, via le faux agent de laravel/ai.
 * La qualité d'extraction et la construction de la pièce envoyée au fournisseur
 * ne sont pas testables ici (hors périmètre, nécessitent le vrai provider).
 */
final class ExtracteurLaravelAiMappingTest extends TestCase
{
    private function document(): DocumentAExtraire
    {
        return new DocumentAExtraire('local', 'documents/tenant/bl.pdf', 'application/pdf', 'bl');
    }

    private function schema(): SchemaExtraction
    {
        return app(RegistreSchemas::class)->pour('bl');
    }

    public function test_mappe_la_sortie_structuree_en_champs(): void
    {
        Storage::fake();

        Ai::fakeAgent(StructuredAnonymousAgent::class, [[
            'numero_bl' => ['valeur' => 'MAEU123', 'confiance' => 0.91, 'zone_source' => 'p.1'],
            // navire_nom absent volontairement (champ du schéma non renvoyé)
            'inconnu' => ['valeur' => 'x', 'confiance' => 1, 'zone_source' => 'p.9'], // hors schéma
        ]]);

        $resultat = (new ExtracteurLaravelAi)->extraire($this->document(), $this->schema());

        $numero = $resultat->champs['numero_bl'];
        $this->assertSame('MAEU123', $numero->valeur);
        $this->assertSame(0.91, $numero->confiance);
        $this->assertSame('p.1', $numero->zoneSource);

        // Champ du schéma non renvoyé → valeur nulle, confiance 0.
        $this->assertArrayHasKey('navire_nom', $resultat->champs);
        $this->assertNull($resultat->champs['navire_nom']->valeur);
        $this->assertSame(0.0, $resultat->champs['navire_nom']->confiance);

        // Champ hors schéma ignoré.
        $this->assertArrayNotHasKey('inconnu', $resultat->champs);

        // Unités consommées par défaut (config).
        $this->assertSame(1, $resultat->unitesConsommees);
    }

    public function test_champ_non_tableau_donne_un_champ_vide(): void
    {
        Storage::fake();

        Ai::fakeAgent(StructuredAnonymousAgent::class, [[
            'numero_bl' => 'pas-un-objet',
        ]]);

        $resultat = (new ExtracteurLaravelAi)->extraire($this->document(), $this->schema());

        $this->assertNull($resultat->champs['numero_bl']->valeur);
        $this->assertSame(0.0, $resultat->champs['numero_bl']->confiance);
        $this->assertNull($resultat->champs['numero_bl']->zoneSource);
    }

    public function test_reponse_non_structuree_leve_une_exception(): void
    {
        Storage::fake();

        // Une réponse texte (non-array) → pas de StructuredAgentResponse.
        Ai::fakeAgent(StructuredAnonymousAgent::class, ['juste du texte']);

        $this->expectException(RuntimeException::class);

        (new ExtracteurLaravelAi)->extraire($this->document(), $this->schema());
    }
}
