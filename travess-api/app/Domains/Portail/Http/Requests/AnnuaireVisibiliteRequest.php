<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AnnuaireVisibiliteRequest extends FormRequest
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
            'public' => ['required', 'boolean'],
        ];
    }
}
