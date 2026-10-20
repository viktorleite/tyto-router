<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use InvalidArgumentException;
use Throwable;
use Override;

final class MiddlewareNotFoundException extends InvalidArgumentException
{
    public function __construct(string $message = 'Middleware not found.', int $code = 0, Throwable|null $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}