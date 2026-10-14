<?php

namespace MobileStock\Gatekeeper\Exceptions;

use RuntimeException;
use Throwable;

class PersonalAccessTokenException extends RuntimeException
{
    public function __construct(
        public readonly string $operation,
        public readonly ?int $status = null,
        ?Throwable $previous = null
    ) {
        $message = "Personal access token $operation failed with status " . ($status ?? 'none');

        parent::__construct($message, 0, $previous);
    }
}
