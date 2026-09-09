<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Requests;

use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Portail\Enums\NiveauAcces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInvitationRequest extends FormRequest
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
            'canal' => ['required', Rule::in([CanalMessage::Whatsapp->value, CanalMessage::Email->value])],
            'destinataire' => ['required', 'string', 'max:255'],
            'niveau' => ['sometimes', Rule::in([NiveauAcces::Limite->value])],
            'client_id' => ['sometimes', 'uuid'],
        ];
    }
}
