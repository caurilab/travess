<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Http\Controllers;

use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Ingestion\Actions\LancerExtraction;
use App\Domains\Ingestion\Actions\ValiderExtraction;
use App\Domains\Ingestion\Http\Requests\ValiderExtractionRequest;
use App\Domains\Ingestion\Http\Resources\ExtractionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Pipeline d'ingestion IA (docs/10) : lancer une extraction (mise en file),
 * consulter la dernière, puis la valider (l'humain corrige, l'écriture métier
 * s'applique). Toute la logique vit dans les Actions ; le contrôleur reste fin.
 */
final class ExtractionController
{
    public function lancer(Document $document, LancerExtraction $lancer): JsonResponse
    {
        Gate::authorize('extraire', $document);

        $extraction = $lancer->executer($document);

        return ExtractionResource::make($extraction)->response()->setStatusCode(202);
    }

    public function derniere(Document $document): ExtractionResource
    {
        Gate::authorize('extraire', $document);

        $extraction = $document->extractions()->latest()->first();

        if ($extraction === null) {
            throw new HttpException(404, 'Aucune extraction pour ce document.');
        }

        return ExtractionResource::make($extraction);
    }

    public function valider(
        ValiderExtractionRequest $requete,
        ExtractionIa $extraction,
        ValiderExtraction $valider,
    ): JsonResponse {
        Gate::authorize('extraire', $extraction->document()->firstOrFail());

        $crees = $valider->executer($extraction, (array) $requete->validated('corrections'));

        return response()->json([
            'extraction' => ExtractionResource::make($extraction->refresh()),
            'crees' => $crees,
        ]);
    }
}
