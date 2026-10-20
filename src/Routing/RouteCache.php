<?php

declare(strict_types=1);

namespace Tyto\Router\Routing;

final class RouteCache
{
    public static function store(string $filePath, array $routes): void
    {
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $export = var_export($routes, true);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$export};\n";

        file_put_contents($filePath, $content, LOCK_EX);
    }

    public static function load(string $filePath): ?array
    {
        if (file_exists($filePath)) {
            $routes = require $filePath;
            return is_array($routes) ? $routes : null;
        }

        return null;
    }

    public static function clear(string $filePath): void
    {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}