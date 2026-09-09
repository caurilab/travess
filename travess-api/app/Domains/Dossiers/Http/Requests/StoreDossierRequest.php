<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Requests;

use App\Domains\Dossiers\Enums\SensDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDossierRequest extends FormRequest
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
            // clients étant scopés par RLS, exists ne matche que le tenant courant.
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
        ];
    }
}
