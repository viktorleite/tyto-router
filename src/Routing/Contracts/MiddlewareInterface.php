<?php

declare(strict_types=1);

namespace Tyto\Router\Routing\Contracts;

interface MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed;
}