<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le tenant cible se nomme « transitaire_id » (jamais « tenant_id », que le
 * middleware rejette) — c'est un choix, pas un scoping.
 */
final class DemanderAssignationRequest extends FormRequest
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
            'transitaire_id' => ['required', 'uuid'],
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
