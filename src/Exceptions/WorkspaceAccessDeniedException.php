<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Exceptions;

use RuntimeException;

class WorkspaceAccessDeniedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No workspace access granted for this OAuth client.');
    }
}
