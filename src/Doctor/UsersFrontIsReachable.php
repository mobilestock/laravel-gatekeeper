<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * /oauth/redirect sends the user to USERS_FRONT_URL, the users-front login page.
 */
class UsersFrontIsReachable extends UsersDiagnostic
{
    public string $name = 'Página de login (users-front) responde';

    protected function probe(): void
    {
        $usersFrontUrl = (string) Config::get('gatekeeper.users_front_url');

        if ($usersFrontUrl === '') {
            throw new RuntimeException('Configure USERS_FRONT_URL.');
        }

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_IN_SECONDS)
                ->timeout(self::TIMEOUT_IN_SECONDS)
                ->get($usersFrontUrl);
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        $status = $response->status();

        if ($status !== 200) {
            throw new RuntimeException("GET USERS_FRONT_URL deveria responder HTTP 200, respondeu HTTP $status.");
        }
    }

    protected function remediation(): string
    {
        $message = 'Confira USERS_FRONT_URL: deve ser a URL pública da página de login (users-front).';

        return $message;
    }
}
