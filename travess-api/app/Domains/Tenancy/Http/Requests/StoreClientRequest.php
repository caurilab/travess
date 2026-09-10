<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation portée par la policy dans le contrôleur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'canaux' => ['nullable', 'array'],
            'canaux.email' => ['nullable', 'email:rfc', 'max:255'],
            'canaux.whatsapp' => ['nullable', 'string', 'max:32'],
        ];
    }
}
