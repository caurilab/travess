<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Http\Requests;

use App\Domains\Alertes\Enums\StatutAlerte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAlerteRequest extends FormRequest
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
            // On avance le statut (vue / traitée), pas de retour à « ouverte ».
            'statut' => ['required', Rule::in([StatutAlerte::Vue->value, StatutAlerte::Traitee->value])],
        ];
    }
}
