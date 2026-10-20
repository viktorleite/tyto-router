<?php

declare(strict_types=1);

namespace Tyto\Router\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class RouteGroup
{
    public function __construct(
        public readonly string $prefix = '',
        public readonly array $middlewares = []
    ) {}
}