<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Http\Requests;

use App\Domains\Conteneurs\Enums\TypeFranchise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFranchiseRequest extends FormRequest
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
            'type' => ['required', Rule::in(TypeFranchise::valeurs())],
            'date_debut' => ['required', 'date'],
            'jours_francs' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}
