<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Policies\DossierPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Point d'entrée du câblage transverse des domaines.
 *
 * Enregistre le contexte tenant (singleton par requête) et, à mesure que les
 * domaines s'étoffent, leurs bindings et providers dédiés. Les routes des
 * domaines sont chargées par routes/api.php à partir de config('domains.list').
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un tenant courant par cycle de requête. Binding « scoped » : sous un
        // runtime persistant (Octane), Laravel vide automatiquement les bindings
        // scoped entre deux requêtes → pas de fuite de contexte inter-requêtes.
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        // Policies (les modèles vivant hors de App\Models, l'auto-découverte ne
        // s'applique pas : on les enregistre explicitement).
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Dossier::class, DossierPolicy::class);
    }
}
