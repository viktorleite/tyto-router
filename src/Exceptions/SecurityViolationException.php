<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use RuntimeException;

final class SecurityViolationException extends RuntimeException
{
    public function __construct(string $message = 'Security violation', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}