<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TokenGuard validates every bearer token with GET USERS_APP_URL/api/me. Without a token,
 * users-api must answer 401: a timeout, a 404 or a 5xx means every signed-in request fails.
 */
class UsersApiIsReachable extends UsersDiagnostic
{
    public string $name = 'users-api responde em /api/me';

    protected function probe(): void
    {
        $usersApiUrl = rtrim((string) Config::get('gatekeeper.users_api_url'), '/');

        if ($usersApiUrl === '') {
            throw new RuntimeException('Configure USERS_APP_URL.');
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_IN_SECONDS)
                ->timeout(self::TIMEOUT_IN_SECONDS)
                ->withoutRedirecting()
                ->get("$usersApiUrl/api/me");
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        $status = $response->status();

        if ($status !== 401) {
            throw new RuntimeException("GET /api/me sem token deveria responder HTTP 401, respondeu HTTP $status.");
        }
    }

    protected function remediation(): string
    {
        $message = 'Confira USERS_APP_URL: deve ser a URL pública da users-api, sem /api no final.';

        return $message;
    }
}
