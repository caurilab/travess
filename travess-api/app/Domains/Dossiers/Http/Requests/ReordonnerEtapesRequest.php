<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReordonnerEtapesRequest extends FormRequest
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
            'ordre' => ['required', 'array', 'min:1'],
            'ordre.*' => ['uuid'],
        ];
    }
}
