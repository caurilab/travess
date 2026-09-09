<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\RoleUtilisateur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Utilisateurs, et infrastructure d'auth (reset password, sessions).
 *
 * L'email est unique au niveau plateforme : il identifie l'utilisateur ET son
 * tenant au login (un utilisateur appartient à un seul tenant). User n'est PAS
 * auto-scopé par TenantScope — il participe à l'amorçage de l'authentification
 * (résolution avant établissement du contexte). Le confinement des listes
 * d'utilisateurs par tenant se fait explicitement (scope forTenant + policy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nom');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default(RoleUtilisateur::Agent->value);
            $table->jsonb('preferences_notif')->default('{}');

            // 2FA (Fortify) : secret TOTP et codes de récupération chiffrés.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->unique('email'); // unicité plateforme
            $table->index('tenant_id');
        });

        $roles = "'".implode("','", RoleUtilisateur::valeurs())."'";
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$roles}))");

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
