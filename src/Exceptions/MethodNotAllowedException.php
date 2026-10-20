<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    public function __construct(string $message = 'Method not allowed for this route.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}