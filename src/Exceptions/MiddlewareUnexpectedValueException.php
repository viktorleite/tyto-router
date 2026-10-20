<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use UnexpectedValueException;

final class MiddlewareUnexpectedValueException extends UnexpectedValueException
{
    public function __construct(string $message = 'The middleware does not implement the required interface.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}