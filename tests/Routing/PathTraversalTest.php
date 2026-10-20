<?php


use Tyto\Router\Exceptions\SecurityViolationException;
use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;

class DummyTraversalController
{
    #[Get('/files/{filename}')]
    public function download(string $filename): string
    {
        return "Downloading: {$filename}";
    }
}

test('o router impede ataques de path traversal e dispara SecurityViolationException', function () {
    $collector = new RouteCollector();
    $collector->addController(DummyTraversalController::class);
    $routes = $collector->compile();

    $router = new Router($routes);

    expect(fn () => $router->dispatch('GET', '/files/foo..bar'))
        ->toThrow(SecurityViolationException::class);
});