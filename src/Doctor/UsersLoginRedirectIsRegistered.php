<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * users-api answers invalid_client both for a wrong secret and for a redirect_uri that is not
 * registered for the client. The probe first confirms the client without redirect_uri and only then
 * sends services.users.redirect, so a rejection here can only mean the redirect URI.
 */
class UsersLoginRedirectIsRegistered extends UsersDiagnostic
{
    public string $name = 'Redirect de login registrado na users-api';

    protected function probe(): void
    {
        $clientId = (string) Config::get('services.users.client_id');
        $clientSecret = (string) Config::get('services.users.client_secret');
        $redirectUri = (string) Config::get('services.users.redirect');

        if (trim($clientId) === '' || trim($clientSecret) === '') {
            throw new DiagnosticSkipped('Depende do cliente de login; veja o diagnóstico do cliente.');
        }

        if ($redirectUri === '') {
            throw new RuntimeException('Configure USERS_REDIRECT_URI.');
        }

        $clientPayload = UsersOAuthProbe::authorizationCodePayload($clientId, $clientSecret);
        $clientResponse = UsersOAuthProbe::send($clientPayload);
        $clientVerdict = UsersOAuthProbe::authorizationCodeVerdict($clientResponse);

        if ($clientVerdict === ClientVerdict::REJECTED) {
            throw new DiagnosticSkipped('A users-api recusou o cliente; veja o diagnóstico do cliente.');
        }

        $redirectPayload = UsersOAuthProbe::authorizationCodePayload($clientId, $clientSecret, $redirectUri);
        $redirectResponse = UsersOAuthProbe::send($redirectPayload);
        $redirectVerdict = UsersOAuthProbe::authorizationCodeVerdict($redirectResponse);

        if ($redirectVerdict === ClientVerdict::REJECTED) {
            throw new RuntimeException("A users-api não tem $redirectUri registrado para o cliente.");
        }
    }

    protected function remediation(): string
    {
        $message = 'Confira USERS_REDIRECT_URI: deve ser APP_URL + /oauth/callback, igual ao redirect do cliente na users-api.';

        return $message;
    }
}
