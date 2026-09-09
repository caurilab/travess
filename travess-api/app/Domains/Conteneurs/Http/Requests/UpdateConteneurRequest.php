<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Requests;

use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateConteneurRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::in(StatutConteneur::valeurs())],
            'type' => ['sometimes', Rule::in(TypeConteneur::valeurs())],
        ];
    }
}
