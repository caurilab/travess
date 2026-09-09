<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Requests;

use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Domains\Conteneurs\Rules\Iso6346Valide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreConteneurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bl_id' => ['required', 'uuid', 'exists:bls,id'],
            // Contrôle serveur ISO 6346 : aucun numéro invalide n'est persisté.
            'numero' => ['required', 'string', new Iso6346Valide],
            'type' => ['required', Rule::in(TypeConteneur::valeurs())],
            'statut' => ['sometimes', Rule::in(StatutConteneur::valeurs())],
            'source_numero' => ['sometimes', Rule::in(SourceNumeroConteneur::valeurs())],
        ];
    }
}
