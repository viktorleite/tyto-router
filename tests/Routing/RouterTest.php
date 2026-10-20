<?php

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\Post;
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;
use Tyto\Router\Exceptions\RouteNotFoundException;

class DummyDispatcherController
{
    #[Get('/home')]
    public function index(): string
    {
        return 'Welcome Home';
    }

    #[Get('/books/{id}')]
    public function show(string $id): string
    {
        return "Book ID: {$id}";
    }

    #[Post('/books')]
    public function store(): string
    {
        return 'Book Created';
    }
}

test('o router despacha uma rota GET estática com sucesso', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyDispatcherController::class);
    $routes = $collector->compile();

    $router = new Router($routes);
    
    // Simula a execução da rota
    $response = $router->dispatch('GET', '/home');

    expect($response)->toBe('Welcome Home');
});

test('o router despacha uma rota GET com parâmetros dinâmicos', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyDispatcherController::class);
    $routes = $collector->compile();

    $router = new Router($routes);

    $response = $router->dispatch('GET', '/books/42');

    expect($response)->toBe('Book ID: 42');
});

test('o router dispara exceção ou status 404 quando a rota não é encontrada', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyDispatcherController::class);
    $routes = $collector->compile();

    $router = new Router($routes);

    expect(fn () => $router->dispatch('GET', '/rota-que-nao-existe'))
        ->toThrow(RouteNotFoundException::class);
});

test('o router dispara RouteNotFoundException quando a rota nao e encontrada', function () {
    $router = new Router([]);

    expect(fn () => $router->dispatch('GET', '/pagina-que-nao-existe'))
        ->toThrow(RouteNotFoundException::class);
});