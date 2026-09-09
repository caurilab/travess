<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Levée lorsqu'une opération sur un modèle scopé par tenant est tentée
 * sans contexte tenant établi.
 *
 * C'est le comportement « fail-closed » du multi-tenant : en l'absence de
 * tenant, on refuse plutôt que de renvoyer ou d'écrire des données de tous
 * les tenants. Ne jamais rattraper cette exception pour l'ignorer : elle
 * signale un défaut d'établissement du contexte (middleware manquant, job
 * lancé sans tenant explicite, etc.).
 */
final class TenantContextMissingException extends RuntimeException
{
    public static function forModel(string $modelClass): self
    {
        return new self(
            "Aucun contexte tenant établi pour une requête sur [{$modelClass}]. "
            .'Le scoping tenant est obligatoire : établir le contexte via le '
            .'middleware EnsureTenantContext (requête HTTP) ou explicitement '
            .'(job, commande, seeder) avant toute opération.'
        );
    }

    public static function forCreation(string $modelClass): self
    {
        return new self(
            "Impossible de créer un [{$modelClass}] sans contexte tenant. "
            .'Le tenant_id est déduit du contexte, jamais fourni par le client.'
        );
    }
}
