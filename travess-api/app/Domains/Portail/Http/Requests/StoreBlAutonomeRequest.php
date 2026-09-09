<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBlAutonomeRequest extends FormRequest
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
            'numero' => ['required', 'string', 'max:255'],
            // Le client saisit un nom d'armateur (une fiche minimale est
            // provisionnée dans son workspace) — il n'a pas d'annuaire d'armateurs.
            'armateur' => ['required', 'string', 'max:255'],
            'navire_nom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'navire_imo' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
