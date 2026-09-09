<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Requests;

use App\Domains\Dossiers\Enums\StatutDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDossierRequest extends FormRequest
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
        // La clôture ne passe pas par ici (endpoint dédié) : statut restreint
        // aux transitions ouvertes.
        $statutsEditables = array_values(array_filter(
            StatutDossier::valeurs(),
            static fn (string $s): bool => $s !== StatutDossier::Cloture->value,
        ));

        return [
            'statut' => ['sometimes', Rule::in($statutsEditables)],
            'motif_blocage' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
