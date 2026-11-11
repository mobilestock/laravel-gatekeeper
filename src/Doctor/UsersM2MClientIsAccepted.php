<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Proves the M2M credentials used by Http::withM2M() (services.users.m2m) without issuing a token:
 * the request adds a deliberately unknown scope, so users-api validates the client and the configured
 * scopes and then answers invalid_scope. Apps with other M2M clients extend it and override clients().
 */
class UsersM2MClientIsAccepted extends UsersDiagnostic
{
    public string $name = 'Cliente M2M aceito pela users-api';

    /**
     * @return list<array{token_url: string, client_id: ?string, client_secret: ?string, scope: ?string}>
     */
    protected function clients(): array
    {
        $m2m = Config::get('services.users.m2m');

        if ($m2m === null) {
            return [];
        }

        $clients = [
            [
                'token_url' => UsersOAuthProbe::tokenUrl(),
                'client_id' => $m2m['client_id'] ?? null,
                'client_secret' => $m2m['client_secret'] ?? null,
                'scope' => $m2m['scope'] ?? null,
            ],
        ];

        return $clients;
    }

    protected function probe(): void
    {
        $clients = $this->clients();

        if ($clients === []) {
            throw new DiagnosticSkipped('O app não usa cliente M2M da users-api.');
        }

        foreach ($clients as $client) {
            $hasCredentials = !empty($client['token_url']) && !empty($client['client_id']) && !empty($client['client_secret']);

            if (!$hasCredentials) {
                throw new RuntimeException('Configure o endpoint e as credenciais M2M da users-api.');
            }

            $payload = UsersOAuthProbe::clientCredentialsPayload(
                $client['client_id'],
                $client['client_secret'],
                $client['scope']
            );
            $response = UsersOAuthProbe::send($payload, $client['token_url']);
            $verdict = UsersOAuthProbe::clientCredentialsVerdict($response);

            if ($verdict === ClientVerdict::REJECTED) {
                throw new RuntimeException('A users-api recusou o cliente M2M (HTTP 401 invalid_client).');
            }
        }
    }

    protected function remediation(): string
    {
        $message = 'Confira USERS_APP_URL, USERS_M2M_CLIENT_ID, USERS_M2M_CLIENT_SECRET e o scope de services.users.m2m.';

        return $message;
    }
}
