<?php

declare(strict_types=1);

namespace Tyto\Router\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path
    ) {}
}