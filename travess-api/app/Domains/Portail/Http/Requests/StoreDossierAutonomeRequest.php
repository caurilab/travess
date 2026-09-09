<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use App\Domains\Dossiers\Enums\SensDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDossierAutonomeRequest extends FormRequest
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
            'sens' => ['required', Rule::in(SensDossier::valeurs())],
        ];
    }
}
