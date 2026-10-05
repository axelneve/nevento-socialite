<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use RuntimeException;

/**
 * The IDP refused or could not be reached. `$errors` holds the field messages of a 422
 * (for example "email already taken in this workspace"), so the app can show them next
 * to the form instead of a generic failure.
 */
class PeopleClientException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, public readonly int $status = 0, public readonly array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
