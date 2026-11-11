<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Proves OAuth clients against users-api without issuing anything. Verified against users-api
 * (laravel/passport 12.4, league/oauth2-server 8.5) on 2026-11-11:
 *
 * - authorization_code with an invalid code: the client is validated first (secret, revoked, grant,
 *   and redirect_uri when sent), so a good client gets HTTP 400 invalid_request about the code and a
 *   bad one gets HTTP 401 invalid_client. The code never decrypts, so no token is issued. An
 *   invalid_request whose hint names another parameter ("Check the `client_id` parameter", e.g. a
 *   client_id that users-api trims to empty) comes before the client validation and proves nothing.
 * - client_credentials with an unknown scope: the client is validated before the scopes, so a good
 *   client gets HTTP 400 invalid_scope and a bad one HTTP 401 invalid_client. The hint names the
 *   first unknown scope, which also proves the configured scopes exist. No token is issued.
 *
 * Only public clients (no secret) skip the client validation; the login and M2M clients are confidential.
 */
final class UsersOAuthProbe
{
    public const INVALID_AUTHORIZATION_CODE = 'laravel-doctor-deliberately-invalid-authorization-code';

    public const INVALID_SCOPE = 'laravel-doctor:deliberately-invalid-scope';

    public static function tokenUrl(): string
    {
        $usersApiUrl = rtrim((string) Config::get('gatekeeper.users_api_url'), '/');
        $tokenUrl = $usersApiUrl === '' ? '' : "$usersApiUrl/oauth/token";

        return $tokenUrl;
    }

    public static function configure(PendingRequest $request): PendingRequest
    {
        $request = $request
            ->asForm()
            ->acceptJson()
            ->connectTimeout(UsersDiagnostic::CONNECT_TIMEOUT_IN_SECONDS)
            ->timeout(UsersDiagnostic::TIMEOUT_IN_SECONDS);

        return $request;
    }

    public static function authorizationCodePayload(string $clientId, string $clientSecret, ?string $redirectUri = null): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => self::INVALID_AUTHORIZATION_CODE,
        ];

        if ($redirectUri !== null) {
            $payload['redirect_uri'] = $redirectUri;
        }

        return $payload;
    }

    public static function clientCredentialsPayload(string $clientId, string $clientSecret, ?string $scope): array
    {
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => trim($scope . ' ' . self::INVALID_SCOPE),
        ];

        return $payload;
    }

    /**
     * Posts to the token endpoint. A 4xx is the expected answer, so a client that throws on errors is unwrapped.
     */
    public static function send(array $payload, ?string $tokenUrl = null): Response
    {
        $tokenUrl ??= self::tokenUrl();

        if ($tokenUrl === '') {
            throw new RuntimeException('Configure USERS_APP_URL.');
        }

        $request = self::configure(Http::createPendingRequest());

        try {
            $response = $request->post($tokenUrl, $payload);
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        return $response;
    }

    public static function authorizationCodeVerdict(Response $response): ClientVerdict
    {
        $status = $response->status();
        $error = $response->json('error');
        $invalidParameter = self::invalidRequestParameter($response);
        $rejectsOnlyTheCode = $error === 'invalid_grant'
            || ($error === 'invalid_request' && in_array($invalidParameter, [null, 'code'], true));

        if ($status === 400 && $rejectsOnlyTheCode) {
            return ClientVerdict::ACCEPTED;
        }

        if ($status === 401 && $error === 'invalid_client') {
            return ClientVerdict::REJECTED;
        }

        throw new RuntimeException(self::unexpected($response));
    }

    public static function clientCredentialsVerdict(Response $response): ClientVerdict
    {
        $status = $response->status();
        $error = $response->json('error');

        if ($status === 401 && $error === 'invalid_client') {
            return ClientVerdict::REJECTED;
        }

        if ($status !== 400 || $error !== 'invalid_scope') {
            throw new RuntimeException(self::unexpected($response));
        }

        $hint = $response->json('hint');
        $hasUnknownScope = is_string($hint) && preg_match('/`([^`\s]+)`/', $hint, $matches) === 1;

        if ($hasUnknownScope && $matches[1] !== self::INVALID_SCOPE) {
            throw new RuntimeException("A users-api não conhece o scope {$matches[1]} configurado.");
        }

        return ClientVerdict::ACCEPTED;
    }

    /**
     * league/oauth2-server names the missing or invalid parameter in the hint of invalid_request
     * ("Check the `client_id` parameter"). Its hints about the code name `code` or no parameter at all
     * ("Cannot decrypt the authorization code").
     */
    private static function invalidRequestParameter(Response $response): ?string
    {
        $hint = $response->json('hint');
        $namesParameter = is_string($hint) && preg_match('/^Check the `([^`]+)` parameter$/', $hint, $matches) === 1;
        $parameter = $namesParameter ? $matches[1] : null;

        return $parameter;
    }

    private static function unexpected(Response $response): string
    {
        $status = $response->status();
        $error = $response->json('error');
        $message = "Resposta inesperada da users-api: HTTP $status";

        if (is_string($error) && preg_match('/^[a-z_]{1,40}$/', $error) === 1) {
            $message .= ", erro $error";
        }

        $message .= '.';

        return $message;
    }
}
