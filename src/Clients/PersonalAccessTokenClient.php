<?php

namespace MobileStock\Gatekeeper\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use MobileStock\Gatekeeper\Action\GetTokenM2MAction;
use MobileStock\Gatekeeper\Data\PersonalAccessToken;
use MobileStock\Gatekeeper\Exceptions\PersonalAccessTokenException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PersonalAccessTokenClient
{
    private const TOKENS_URL = 'api/users/{userId}/personal-access-tokens';

    public function create(int $userId, string $name): PersonalAccessToken
    {
        $response = $this->send(
            'create',
            'POST',
            self::TOKENS_URL,
            ['userId' => $userId],
            [
                'json' => ['name' => $name],
            ]
        );
        if (!$response->successful()) {
            throw new PersonalAccessTokenException('create', $response->status());
        }

        $data = $response->json();
        $personalAccessToken = new PersonalAccessToken(
            $data['id'],
            $data['name'],
            $data['scopes'],
            $data['created_at'],
            $data['token']
        );

        return $personalAccessToken;
    }

    /**
     * @return PersonalAccessToken[]
     */
    public function list(int $userId): array
    {
        $response = $this->send('list', 'GET', self::TOKENS_URL, ['userId' => $userId]);
        if (!$response->successful()) {
            throw new PersonalAccessTokenException('list', $response->status());
        }

        $personalAccessTokens = array_map(
            fn(array $data) => new PersonalAccessToken(
                $data['id'],
                $data['name'],
                $data['scopes'],
                $data['created_at']
            ),
            $response->json()
        );

        return $personalAccessTokens;
    }

    public function delete(int $userId, string $tokenId): void
    {
        $response = $this->send('delete', 'DELETE', self::TOKENS_URL . '/{tokenId}', [
            'userId' => $userId,
            'tokenId' => $tokenId,
        ]);
        $isAlreadyDeleted =
            $response->status() === HttpResponse::HTTP_NOT_FOUND && $response->json('message') === 'Not found.';
        if ($response->successful() || $isAlreadyDeleted) {
            return;
        }

        throw new PersonalAccessTokenException('delete', $response->status());
    }

    private function send(
        string $operation,
        string $method,
        string $url,
        array $urlParameters,
        array $options = []
    ): Response {
        try {
            $m2mToken = GetTokenM2MAction::execute();
            if (empty($m2mToken)) {
                throw new PersonalAccessTokenException($operation);
            }

            $response = Http::baseUrl(Config::get('gatekeeper.users_api_url'))
                ->connectTimeout(5)
                ->timeout(30)
                ->acceptJson()
                ->withM2M()
                ->withUrlParameters($urlParameters)
                ->send($method, $url, $options);
        } catch (ConnectionException $exception) {
            throw new PersonalAccessTokenException($operation, null, $exception);
        }

        return $response;
    }
}
