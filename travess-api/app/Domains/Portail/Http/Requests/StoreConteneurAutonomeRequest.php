<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Domains\Conteneurs\Rules\Iso6346Valide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreConteneurAutonomeRequest extends FormRequest
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
            'numero' => ['required', 'string', new Iso6346Valide],
            'type' => ['required', Rule::in(TypeConteneur::valeurs())],
        ];
    }
}
