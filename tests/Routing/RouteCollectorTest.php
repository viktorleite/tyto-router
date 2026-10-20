<?php

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\Post;
use Tyto\Router\Routing\Attributes\Middleware;
use Tyto\Router\Routing\RouteCollector;

class DummyCollectorController
{
    #[Get('/books')]
    public function index(): void {}

    #[Get('/books/{id}')]
    public function show(int $id): void {}


    #[Post('/books')]
    public function store(): void {}
    // #[Put()]
    // #[Delete()]
}

test('o coletor de rotas compila corretamente os atributos dos controllers', function () {
    $collector = new RouteCollector();

    $collector->addController(DummyCollectorController::class);

    $routes = $collector->compile();

    expect($routes)->toBeArray()->toHaveKeys(['GET', 'POST']);
    // Verificar se a rota GET foi mapeada
    expect($routes['GET'])->not->toBeEmpty();

    // Verificar se a rota com parametro dinamico /books/{id} gerou a regex e extraiu o parametro id
    $foundDynamicRoute = false;

    foreach($routes['GET'] as $route) {
        if(
            isset($route['params']) && 
            in_array('id', $route['params'], true)
        ) {
            $foundDynamicRoute = true;
            expect($route['action'])->toBe('show');
            expect($route['controller'])->toBe(DummyCollectorController::class);
        }
    }

    expect($foundDynamicRoute)->toBeTrue();

});

class DummyMiddlewareForTest 
{
    public function handle($request, callable $next) {
        return $next($request);
    }
}

class DummyCollectorControllerWithMiddleware
{
    #[Get('/protected-route')]
    #[Middleware(DummyMiddlewareForTest::class)]
    public function secureAction(): void {}
}

test('o coletor de rotas compila corretamente os atributos de middleware', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyCollectorControllerWithMiddleware::class);
    
    $routes = $collector->compile();

    expect($routes['GET'])->not->toBeEmpty();
    
    $route = $routes['GET'][0];
    
    expect($route)->toHaveKey('middlewares');
    expect($route['middlewares'])->toBe([DummyMiddlewareForTest::class]);
});