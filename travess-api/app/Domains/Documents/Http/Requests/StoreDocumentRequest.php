<?php

declare(strict_types=1);

namespace App\Domains\Documents\Http\Requests;

use App\Domains\Documents\Enums\TypeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDocumentRequest extends FormRequest
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
            'dossier_id' => ['required', 'uuid', 'exists:dossiers,id'],
            'type' => ['required', Rule::in(TypeDocument::valeurs())],
            'fichier' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // 10 Mo
        ];
    }
}
