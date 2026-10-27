<?php

declare(strict_types=1);

namespace Tyto\Router\Routing;

use Closure;
use Tyto\Router\Exceptions\ActionNotFoundInController;
use Tyto\Router\Exceptions\ControllerNotFoundException;
use Tyto\Router\Exceptions\InvalidRouteHandlerException;
use Tyto\Router\Exceptions\MethodNotFoundException;
use Tyto\Router\Exceptions\MiddlewareNotFoundException;
use Tyto\Router\Exceptions\MiddlewareUnexpectedValueException;
use Tyto\Router\Exceptions\RouteNotFoundException;
use Tyto\Router\Exceptions\SecurityViolationException;
use Tyto\Router\Routing\Contracts\MiddlewareInterface;

final class Router
{
    public function __construct(
        private readonly array $routes = []
    ) {}

    public function dispatch(string $method, string $uri, mixed $request = null): mixed
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        $method = strtoupper($method);

        if (!isset($this->routes[$method])) {
            throw new RouteNotFoundException("The HTTP method [{$method}] is not supported.");
        }

        $matchedRoute = null;
        $params = [];

        foreach ($this->routes[$method] as $route) {
            if (isset($route['pattern']) && preg_match($route['pattern'], $path, $matches)) {
                $matchedRoute = $route;

                if (isset($route['params']) && is_array($route['params'])) {
                    foreach ($route['params'] as $paramName) {
                        if (isset($matches[$paramName])) {
                            $decodedValue = urldecode($matches[$paramName]);

                            if (
                                str_contains($decodedValue, '../')
                                || str_contains($decodedValue, '..\\')
                                || str_contains($decodedValue, '..')
                            ) {
                                /** TODO: Se retornar uma mensagem é perigoso pra segurança? */
                                throw new SecurityViolationException('Security violation: Path traversal sequence detected.');
                            }

                            $params[$paramName] = $decodedValue;
                        }
                    }
                }
                break;
            }
        }
        if (!$matchedRoute) {
            throw new RouteNotFoundException("No route matched the given URI: [{$path}]");
        }
        return $this->invoke(
            $matchedRoute['controller'],
            $matchedRoute['action'] ?? null,
            $params,
            $matchedRoute['middleware'] ?? $matchedRoute['middlewares'] ?? [],
            $request
        );
    }

    private function invoke(
        mixed $handler,
        ?string $action,
        array $params,
        array $middlewares,
        mixed $request
    ): mixed {
        $destination = function () use ($handler, $action, $params, $request) {
            $executeHandler = function ($object, string $methodName) use ($params, $request) {
                $reflection = new \ReflectionMethod($object, $methodName);
                $args = [];

                foreach ($reflection->getParameters() as $parameter) {
                    $paramType = $parameter->getType();
                    $paramName = $parameter->getName();

                    if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                        $typeName = $paramType->getName();
                        if ($request !== null && ($request instanceof $typeName || is_a($request, $typeName, true))) {
                            $args[] = $request;
                            continue;
                        }
                    }
                    if (array_key_exists($paramName, $params)) {
                        $value = $params[$paramName];
                        // TODO: verificação do tipo esperado pelo método (usando ReflectionParameter)
                        if ($paramType instanceof \ReflectionNamedType && $paramType->getName() === 'int') {
                            $value = (int) $value;
                        } elseif ($paramType instanceof \ReflectionNamedType && $paramType->getName() === 'float') {
                            $value = (float) $value;
                        }
                        
                        $args[] = $value;
                        continue;
                    }

                    if (!empty($params)) {
                        $args[] = array_shift($params);
                        continue;
                    }

                    if ($parameter->isDefaultValueAvailable()) {
                        $args[] = $parameter->getDefaultValue();
                        break;
                    }
                }

                return $object->{$methodName}(...$args);
            };

            if (is_array($handler) && isset($handler[0]) && is_string($handler[0]) && str_contains($handler[0], '@')) {
                [$controllerClass, $methodName] = explode('@', $handler[0], 2);
                $this->validateControllerAndMethod($controllerClass, $methodName);
                return $executeHandler(new $controllerClass(), $methodName);
            }

            if (is_array($handler) && isset($handler[0]) && is_object($handler[0])) {
                [$object, $methodName] = $handler;
                if (!method_exists($object, $methodName)) {
                    throw new MethodNotFoundException("Method [{$methodName}] not found in object.");
                }
                return $executeHandler($object, $methodName);
            }

            if (is_string($handler) && str_contains($handler, '@')) {
                [$controllerClass, $methodName] = explode('@', $handler, 2);
                $this->validateControllerAndMethod($controllerClass, $methodName);
                return $executeHandler(new $controllerClass(), $methodName);
            }

            $controllerClass = $handler;
            if (!is_string($controllerClass) || !class_exists($controllerClass)) {
                throw new ControllerNotFoundException("Controller [{$controllerClass}] not found or invalid handler type.");
            }

            $controllerClassInstance = new $controllerClass();
            if ($action === null || !method_exists($controllerClassInstance, $action)) {
                throw new ActionNotFoundInController("Action [{$action}] not found in controller [{$controllerClass}]");
            }

            return $executeHandler($controllerClassInstance, $action);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, string $middlewareClass) use ($request) {
                return function () use ($middlewareClass, $next, $request) {
                    if (!class_exists($middlewareClass)) {
                        throw new MiddlewareNotFoundException("Middleware [{$middlewareClass}] not found.");
                    }

                    $middleware = new $middlewareClass();

                    if (!$middleware instanceof MiddlewareInterface) {
                        throw new MiddlewareUnexpectedValueException("Middleware [{$middlewareClass}] must implement " . MiddlewareInterface::class);
                    }

                    return $middleware->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline();
    }

    private function validateControllerAndMethod(string $controllerClass, string $methodName): void
    {
        if (!class_exists($controllerClass)) {
            throw new ControllerNotFoundException("Controller [{$controllerClass}] not found.");
        }
        $controllerClassInstance = new $controllerClass();
        if (!method_exists($controllerClassInstance, $methodName)) {
            throw new ActionNotFoundInController("Action [{$methodName}] not found in controller [{$controllerClass}]");
        }
    }
}
