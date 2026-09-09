<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RefuserAssignationRequest extends FormRequest
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
            'motif' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
