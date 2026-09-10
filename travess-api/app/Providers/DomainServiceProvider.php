<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Alertes\Policies\AlertePolicy;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Conteneurs\Policies\BlPolicy;
use App\Domains\Conteneurs\Policies\ConteneurPolicy;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Correspondance\Policies\MessagePolicy;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Policies\DocumentPolicy;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Models\Etape;
use App\Domains\Dossiers\Policies\DossierPolicy;
use App\Domains\Dossiers\Policies\EtapePolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Ingestion\Adapters\ExtracteurFactice;
use App\Domains\Ingestion\Adapters\ExtracteurLaravelAi;
use App\Domains\Ingestion\Contracts\ExtracteurDocument;
use App\Domains\Messagerie\Adapters\ExpediteurFactice;
use App\Domains\Messagerie\Adapters\ServiceOtpFactice;
use App\Domains\Messagerie\Contracts\ServiceOtp;
use App\Domains\Surestaries\Policies\FranchisePolicy;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Policies\ClientPolicy;
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

        // Extracteur documentaire : fournisseur isolé derrière l'interface
        // (principe n°9). « factice » par défaut (dev/tests sans clé).
        $this->app->bind(ExtracteurDocument::class, static fn (): ExtracteurDocument => config('ia.driver') === 'laravel_ai'
            ? new ExtracteurLaravelAi()
            : new ExtracteurFactice());

        // Messagerie sortante & OTP (onboarding portail), fournisseur isolé
        // (principe n°9). Factice par défaut : flux testable sans réseau ni clé.
        // L'expéditeur factice est un singleton (journal partagé test ↔ code).
        $this->app->singleton(ExpediteurFactice::class);
        $this->app->bind(ServiceOtp::class, static fn ($app): ServiceOtp => $app->make(ServiceOtpFactice::class));
    }

    public function boot(): void
    {
        // Policies (les modèles vivant hors de App\Models, l'auto-découverte ne
        // s'applique pas : on les enregistre explicitement).
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Dossier::class, DossierPolicy::class);
        Gate::policy(Etape::class, EtapePolicy::class);
        Gate::policy(Bl::class, BlPolicy::class);
        Gate::policy(Conteneur::class, ConteneurPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Franchise::class, FranchisePolicy::class);
        Gate::policy(Alerte::class, AlertePolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
    }
}
