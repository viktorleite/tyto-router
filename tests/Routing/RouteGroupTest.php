<?php

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\RouteGroup;
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;

#[RouteGroup(prefix: '/api/v1')]
class DummyApiV1Controller
{
    #[Get('/users')]
    public function index(): string
    {
        return 'API Users List';
    }

    #[Get('/users/{id}')]
    public function show(string $id): string
    {
        return "API User ID: {$id}";
    }
}

test('o router suporta grupos de rotas com prefixos', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyApiV1Controller::class);
    $routes = $collector->compile();

    $router = new Router($routes);

    $responseList = $router->dispatch('GET', '/api/v1/users');
    expect($responseList)->toBe('API Users List');

    $responseShow = $router->dispatch('GET', '/api/v1/users/99');
    expect($responseShow)->toBe('API User ID: 99');
});