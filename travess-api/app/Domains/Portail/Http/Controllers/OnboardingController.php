<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Portail\Actions\ConfirmerInvitation;
use App\Domains\Portail\Actions\ReclamerInvitation;
use App\Domains\Portail\Http\Requests\ConfirmerInvitationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Onboarding PUBLIC (ADR-013, 7.2) : réclamation d'une invitation (envoi OTP)
 * puis confirmation (OTP → provisionnement compte + activation de l'accès).
 * Aucune auth ; la sécurité repose sur l'URL signée, le token secret, la RLS
 * bornée au token et l'OTP.
 */
final class OnboardingController
{
    public function montrer(string $token, ReclamerInvitation $reclamer): JsonResponse
    {
        return response()->json($reclamer->executer($token));
    }

    public function confirmer(string $token, ConfirmerInvitationRequest $requete, ConfirmerInvitation $confirmer): JsonResponse
    {
        return response()->json($confirmer->executer($token, $requete->validated()), 201);
    }
}
