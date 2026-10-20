<?php

declare(strict_types=1);

namespace Tyto\Router\Exceptions;

use BadMethodCallException;

final class ActionNotFoundInController extends BadMethodCallException
{
    public function __construct(string $message = 'Action not found in controller.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}