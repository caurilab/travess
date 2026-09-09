<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Requests;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Shared\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignerAgentsRequest extends FormRequest
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
        // users n'étant pas scopé par RLS, on restreint explicitement au tenant.
        $tenantId = app(TenantContext::class)->idOrFail();

        return [
            'agents' => ['present', 'array'],
            'agents.*' => [
                'uuid',
                // Seuls des utilisateurs internes assignables (gérant/agent) du tenant.
                Rule::exists('users', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('role', [RoleUtilisateur::Gerant->value, RoleUtilisateur::Agent->value]),
            ],
        ];
    }
}
