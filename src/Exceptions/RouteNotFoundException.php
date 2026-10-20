<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use RuntimeException;

final class RouteNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Route not found.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}