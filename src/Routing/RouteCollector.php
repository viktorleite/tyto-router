<?php

declare(strict_types=1);

namespace Tyto\Router\Routing;

use ReflectionAttribute;
use ReflectionClass;
use Tyto\Router\Routing\Attributes\Middleware;
use Tyto\Router\Routing\Attributes\Route;
use Tyto\Router\Routing\Attributes\RouteGroup;

final class RouteCollector
{
    /** @var array<string> */
    private array $controllers = [];

    public function addController(string $controllerClass): void
    {
        $this->controllers[] = $controllerClass;
    }

    public function compile(): array
    {
        $compiledRoutes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => []
        ];

        foreach ($this->controllers as $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);

            $groupPrefix = '';
            $groupMiddlewares = [];

            $groupAttributes = $reflection->getAttributes(RouteGroup::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($groupAttributes)) {
                /** @var RouteGroup $groupInstance */
                $groupInstance = $groupAttributes[0]->newInstance();
                $groupPrefix = rtrim($groupInstance->prefix, '/');
                $groupMiddlewares = $groupInstance->middlewares;
            }

            foreach ($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

                foreach ($attributes as $attribute) {
                    /** @var Route $routeAttribute */
                    $routeAttribute = $attribute->newInstance();

                    $attributeClass = $attribute->getName();
                    $shortName = (new ReflectionClass($attributeClass))->getShortName();
                    $httpMethod = strtoupper($shortName);

                    $uriPattern = $groupPrefix . '/' . ltrim($routeAttribute->path, '/');
                    $uriPattern = '/' . ltrim($uriPattern, '/');

                    $middlewares = $groupMiddlewares;
                    $middlewareAttributes = $method->getAttributes(Middleware::class, ReflectionAttribute::IS_INSTANCEOF);
                    foreach ($middlewareAttributes as $mwAttribute) {
                        /** @var Middleware $mwInstance */
                        $mwInstance = $mwAttribute->newInstance();
                        if (!in_array($mwInstance->middlewareClass, $middlewares)) {
                            $middlewares[] = $mwInstance->middlewareClass;
                        }
                    }

                    $paramNames = [];
                    $regexPattern = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$paramNames) {
                        $paramNames[] = $m[1];
                        return '(?P<' . $m[1] . '>[^/]+)';
                    }, $uriPattern);

                    $regexPattern = '#^' . $regexPattern . '$#';

                    if (!isset($compiledRoutes[$httpMethod])) {
                        $compiledRoutes[$httpMethod] = [];
                    }

                    $compiledRoutes[$httpMethod][] = [
                        'controller'  => $controllerClass,
                        'action'      => $method->getName(),
                        'params'      => $paramNames,
                        'pattern'     => $regexPattern,
                        'original'    => $uriPattern,
                        'middlewares' => $middlewares
                    ];
                }
            }
        }

        return $compiledRoutes;
    }
}
