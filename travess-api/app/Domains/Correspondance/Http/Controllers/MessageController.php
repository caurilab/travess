<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Http\Controllers;

use App\Domains\Correspondance\Actions\CreerEtEnvoyerMessage;
use App\Domains\Correspondance\Actions\GenererBrouillon;
use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Correspondance\Http\Requests\CreerMessageRequest;
use App\Domains\Correspondance\Http\Resources\MessageResource;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Correspondance armateur d'un dossier (docs/10 §12) : fil, brouillon
 * pré-rempli, et création+envoi (mis en file, 202). Contrôleur fin ; la logique
 * vit dans les Actions.
 */
final class MessageController
{
    public function index(Request $requete, Dossier $dossier): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Message::class);

        $messages = QueryBuilder::for($dossier->messages()->getQuery())
            ->allowedFilters(
                AllowedFilter::exact('direction'),
                AllowedFilter::exact('canal'),
                AllowedFilter::exact('type_demande'),
                AllowedFilter::exact('armateur_id'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate(min(100, max(1, (int) $requete->integer('per_page', 25))))
            ->appends($requete->query());

        return MessageResource::collection($messages);
    }

    public function store(CreerMessageRequest $requete, Dossier $dossier, CreerEtEnvoyerMessage $creer): JsonResponse
    {
        Gate::authorize('create', Message::class);

        $message = $creer->executer($dossier, $requete->user(), $requete->validated());

        return MessageResource::make($message)->response()->setStatusCode(202);
    }

    public function brouillon(Request $requete, Dossier $dossier, GenererBrouillon $generer): JsonResponse
    {
        Gate::authorize('viewAny', Message::class);

        $valide = $requete->validate([
            'type_demande' => ['required', Rule::in(TypeDemande::valeurs())],
        ]);

        return response()->json([
            'data' => $generer->executer($dossier, TypeDemande::from($valide['type_demande'])),
        ]);
    }

    public function show(Message $message): MessageResource
    {
        Gate::authorize('view', $message);

        return MessageResource::make($message);
    }
}
