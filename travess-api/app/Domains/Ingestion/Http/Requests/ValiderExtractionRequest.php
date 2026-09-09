<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrections humaines appliquées aux champs proposés par l'IA. Le format des
 * champs dépend du type de document ; on valide seulement l'enveloppe (map de
 * corrections), l'applicateur du domaine contrôle le reste.
 */
final class ValiderExtractionRequest extends FormRequest
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
            'corrections' => ['present', 'array'],
        ];
    }
}
