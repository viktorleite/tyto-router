<?php

declare(strict_types=1);

namespace Tyto\Router\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Get extends Route
{
}