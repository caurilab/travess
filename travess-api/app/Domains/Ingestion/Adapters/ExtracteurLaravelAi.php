<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Adapters;

use App\Domains\Ingestion\Contracts\ExtracteurDocument;
use App\Domains\Ingestion\Data\ChampExtrait;
use App\Domains\Ingestion\Data\DocumentAExtraire;
use App\Domains\Ingestion\Data\ResultatExtraction;
use App\Domains\Ingestion\Data\SchemaExtraction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\StructuredAnonymousAgent;
use RuntimeException;

/**
 * Adaptateur d'extraction réel : Claude via laravel/ai (principe n°9, isolé
 * derrière ExtracteurDocument). Le document est envoyé au fournisseur en base64
 * inline (jamais d'URL publique) et la réponse est contrainte par un schéma de
 * sortie structurée où chaque champ porte sa valeur, sa confiance et sa zone
 * source — même forme que ChampExtrait. Aucune écriture métier ici : l'IA
 * propose, l'humain valide (principe n°5).
 *
 * NB confidentialité : la non-rétention des documents côté fournisseur dépend de
 * la configuration du compte Anthropic (zero data retention), pas de cet appel.
 * À verrouiller avec l'intégrateur externe avant prod (cf. config/ia.php).
 */
final class ExtracteurLaravelAi implements ExtracteurDocument
{
    public function extraire(DocumentAExtraire $document, SchemaExtraction $schema): ResultatExtraction
    {
        $agent = new StructuredAnonymousAgent(
            instructions: $this->instructions($schema),
            messages: [],
            tools: [],
            schema: fn (JsonSchema $s): array => $this->schemaSortie($s, $schema),
        );

        $reponse = $agent->prompt(
            $this->consigne($schema),
            [$this->piece($document)],
            config('ia.provider'),
            (string) config('ia.modele'),
        );

        if (! $reponse instanceof StructuredAgentResponse) {
            throw new RuntimeException("Réponse d'extraction non structurée.");
        }

        return new ResultatExtraction(
            $this->champs($reponse->toArray(), $schema),
            (int) config('ia.unites_par_document', 1),
        );
    }

    /**
     * Consigne système : rôle et exigences de fiabilité.
     */
    private function instructions(SchemaExtraction $schema): string
    {
        return implode(' ', [
            "Tu es un assistant d'extraction documentaire pour un transitaire.",
            "Tu lis le document fourni (type : {$schema->typeDocument}) et tu renseignes",
            'strictement les champs demandés. Ne devine jamais : si une information',
            'est absente ou illisible, mets la valeur à null et une confiance basse.',
            'La confiance est un réel entre 0 et 1. zone_source situe la donnée dans',
            'le document (ex. « p.1, cartouche »).',
        ]);
    }

    private function consigne(SchemaExtraction $schema): string
    {
        return 'Extrais les champs du document ci-joint en respectant le schéma de sortie.';
    }

    private function piece(DocumentAExtraire $document): StoredImage|StoredDocument
    {
        return $this->estImage($document->mime)
            ? new StoredImage($document->chemin, $document->disque)
            : new StoredDocument($document->chemin, $document->disque);
    }

    private function estImage(?string $mime): bool
    {
        return $mime !== null && str_starts_with($mime, 'image/');
    }

    /**
     * Schéma de sortie : un objet par champ {valeur, confiance, zone_source}.
     *
     * @return array<string, mixed>
     */
    private function schemaSortie(JsonSchema $s, SchemaExtraction $schema): array
    {
        $sortie = [];

        foreach ($schema->champs as $nom => $type) {
            $sortie[$nom] = $s->object([
                'valeur' => $this->typeValeur($s, $type)->nullable()->required(),
                'confiance' => $s->number()->min(0)->max(1)->required(),
                'zone_source' => $s->string()->nullable()->required(),
            ])->required();
        }

        return $sortie;
    }

    /**
     * Traduit le type indicatif du schéma métier en type JSON Schema.
     */
    private function typeValeur(JsonSchema $s, string $type): Type
    {
        return match ($type) {
            'number' => $s->number(),
            'liste' => $s->array()->items($s->string()),
            default => $s->string(), // string, date (ISO en texte), etc.
        };
    }

    /**
     * Convertit la sortie structurée en champs domaine (ChampExtrait).
     *
     * @param  array<string, mixed>  $sortie
     * @return array<string, ChampExtrait>
     */
    private function champs(array $sortie, SchemaExtraction $schema): array
    {
        $champs = [];

        foreach (array_keys($schema->champs) as $nom) {
            $brut = $sortie[$nom] ?? null;

            if (! is_array($brut)) {
                $champs[$nom] = new ChampExtrait(null, 0.0, null);

                continue;
            }

            $champs[$nom] = new ChampExtrait(
                $brut['valeur'] ?? null,
                (float) ($brut['confiance'] ?? 0.0),
                isset($brut['zone_source']) ? (string) $brut['zone_source'] : null,
            );
        }

        return $champs;
    }
}
