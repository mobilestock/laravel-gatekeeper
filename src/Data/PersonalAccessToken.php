<?php

namespace MobileStock\Gatekeeper\Data;

readonly class PersonalAccessToken
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $scopes,
        public string $createdAt,
        public ?string $token = null
    ) {
    }
}
