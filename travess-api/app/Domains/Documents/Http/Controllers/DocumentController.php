<?php

declare(strict_types=1);

namespace App\Domains\Documents\Http\Controllers;

use App\Domains\Documents\Actions\DeposerDocument;
use App\Domains\Documents\Http\Requests\StoreDocumentRequest;
use App\Domains\Documents\Http\Resources\DocumentResource;
use App\Domains\Documents\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dépôt et consultation des documents (docs/10 §9). L'ingestion IA (extraction,
 * validation) est le Lot 5 ; ici, stockage et téléchargement seulement.
 */
final class DocumentController
{
    public function store(StoreDocumentRequest $requete, DeposerDocument $deposer): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $document = $deposer->executer(
            ['dossier_id' => $requete->validated('dossier_id'), 'type' => $requete->validated('type')],
            $requete->file('fichier'),
        );

        return DocumentResource::make($document)->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return DocumentResource::make($document);
    }

    public function telecharger(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return Storage::download($document->chemin_stockage, $document->nom_original);
    }
}
