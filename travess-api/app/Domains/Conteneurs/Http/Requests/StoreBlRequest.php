<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBlRequest extends FormRequest
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
        // dossiers et armateurs sont scopés par RLS : exists ne matche que le tenant.
        return [
            'dossier_id' => ['required', 'uuid', 'exists:dossiers,id'],
            'numero' => ['required', 'string', 'max:50'],
            'armateur_id' => ['required', 'uuid', 'exists:armateurs,id'],
            'navire_nom' => ['nullable', 'string', 'max:120'],
            'navire_imo' => ['nullable', 'string', 'max:20'],
        ];
    }
}
