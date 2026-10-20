<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use InvalidArgumentException;

class InvalidRouteHandlerException extends InvalidArgumentException
{
    public function __construct(string $message = 'Invalid route handler structure provided.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}