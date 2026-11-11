<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * /oauth/callback exchanges the code at USERS_APP_URL/oauth/token with services.users.client_id and
 * client_secret. The probe sends a deliberately invalid code without redirect_uri, so the answer
 * depends only on the client; UsersLoginRedirectIsRegistered checks the redirect URI.
 */
class UsersLoginClientIsAccepted extends UsersDiagnostic
{
    public string $name = 'Cliente de login aceito pela users-api';

    protected function probe(): void
    {
        $clientId = (string) Config::get('services.users.client_id');
        $clientSecret = (string) Config::get('services.users.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Configure USERS_CLIENT_ID e USERS_CLIENT_SECRET.');
        }

        $payload = UsersOAuthProbe::authorizationCodePayload($clientId, $clientSecret);
        $response = UsersOAuthProbe::send($payload);
        $verdict = UsersOAuthProbe::authorizationCodeVerdict($response);

        if ($verdict === ClientVerdict::REJECTED) {
            throw new RuntimeException('A users-api recusou o cliente (HTTP 401 invalid_client).');
        }
    }

    protected function remediation(): string
    {
        $message = 'Confira USERS_CLIENT_ID e USERS_CLIENT_SECRET: devem ser de um cliente de login ativo da users-api.';

        return $message;
    }
}
