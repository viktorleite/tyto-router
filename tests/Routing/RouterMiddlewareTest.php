<?php

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\Middleware;
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;
use Tyto\Router\Routing\Contracts\MiddlewareInterface;

class TestAuthMiddleware implements MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        // Simula uma falha de autenticação
        if ($request === 'unauthorized') {
            return 'Unauthorized Access';
        }

        // Continua para o próximo middleware ou controller
        return $next($request);
    }
}

class DummyMiddlewareController
{
    #[Get('/secure-data')]
    #[Middleware(TestAuthMiddleware::class)]
    public function index(): string
    {
        return 'Secure Content Loaded';
    }
}

test('o router executa o middleware com sucesso e permite acesso', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyMiddlewareController::class);
    $routes = $collector->compile();

    $router = new Router($routes);
    
    $response = $router->dispatch('GET', '/secure-data', 'authorized');

    expect($response)->toBe('Secure Content Loaded');
});

test('o router intercepta a requisição quando o middleware bloqueia', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyMiddlewareController::class);
    $routes = $collector->compile();

    $router = new Router($routes);
    
    $response = $router->dispatch('GET', '/secure-data', 'unauthorized');

    expect($response)->toBe('Unauthorized Access');
});