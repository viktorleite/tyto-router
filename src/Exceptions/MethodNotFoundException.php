<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use BadMethodCallException;

final class MethodNotFoundException extends BadMethodCallException
{
    public function __construct(string $message = 'Method not found.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}