<?php

declare(strict_types=1);

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de base commun à tout le métier Travess.
 *
 * Clés primaires en UUID v7 (ordonnées dans le temps → bonne localité d'index).
 * Le trait HasUuids de Laravel 13 génère nativement des UUID v7.
 */
abstract class BaseModel extends Model
{
    use HasFactory;
    use HasUuids;
}
