<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFranchiseRequest extends FormRequest
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
            'date_debut' => ['sometimes', 'date'],
            'jours_francs' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ];
    }
}
