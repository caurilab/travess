<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Rules;

use App\Domains\Conteneurs\Support\Iso6346;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de validation : numéro de conteneur ISO 6346 valide (après
 * normalisation). Délègue au port PHP Iso6346 — source de vérité serveur,
 * jamais réimplémentée ici.
 */
final class Iso6346Valide implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Iso6346::estValide(Iso6346::normaliser($value))) {
            $fail('Le numéro de conteneur n\'est pas conforme à la norme ISO 6346.');
        }
    }
}
