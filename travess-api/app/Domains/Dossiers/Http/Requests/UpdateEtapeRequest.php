<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Requests;

use App\Domains\Dossiers\Enums\StatutEtape;
use App\Shared\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEtapeRequest extends FormRequest
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
        $tenantId = app(TenantContext::class)->idOrFail();

        return [
            'statut' => ['sometimes', Rule::in(StatutEtape::valeurs())],
            'sla_jours' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'date_prevue' => ['sometimes', 'nullable', 'date'],
            'date_reelle' => ['sometimes', 'nullable', 'date'],
            'responsable_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
