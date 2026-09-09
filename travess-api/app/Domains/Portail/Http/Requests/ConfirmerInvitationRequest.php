<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmerInvitationRequest extends FormRequest
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
            'code_otp' => ['required', 'string'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'mot_de_passe' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
