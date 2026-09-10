<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Http\Requests;

use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Messagerie\Enums\CanalMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreerMessageRequest extends FormRequest
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
            // Existence vérifiée dans le scope tenant (RLS) : un id d'un autre
            // tenant est invisible, donc rejeté.
            'armateur_id' => ['nullable', 'uuid', Rule::exists('armateurs', 'id')],
            // Canal e-mail uniquement au premier lot (whatsapp/sms différés, sans
            // fournisseur agréé) : évite un état « en file » fantôme (audit R4).
            'canal' => ['required', Rule::in([CanalMessage::Email->value])],
            'type_demande' => ['nullable', Rule::in(TypeDemande::valeurs())],
            // Format e-mail validé (audit B2). L'adresse effective est arbitrée
            // côté Action (armateur prioritaire, libre réservé au gérant).
            'destinataire_adresse' => ['nullable', 'email:rfc', 'max:255'],
            'objet' => ['required', 'string', 'max:255'],
            'corps' => ['required', 'string', 'max:20000'],
        ];
    }
}
