<?php

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\RouteCache;
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;

class DummyCacheController
{
    #[Get('/cached-route')]
    public function index(): string
    {
        return 'Cached Response';
    }
}

test('o route cache consegue salvar, carregar e limpar o arquivo de cache corretamente', function () {
    $cachePath = sys_get_temp_dir() . '/tyto_routes_test_' . uniqid() . '.php';

    expect(RouteCache::load($cachePath))->toBeNull();

    $collector = new RouteCollector();
    $collector->addController(DummyCacheController::class);
    $routes = $collector->compile();

    RouteCache::store($cachePath, $routes);
    expect(file_exists($cachePath))->toBeTrue();

    $cachedRoutes = RouteCache::load($cachePath);
    expect($cachedRoutes)->toBeArray()
        ->and($cachedRoutes)->toHaveKey('GET');

    $router = new Router($cachedRoutes);
    $response = $router->dispatch('GET', '/cached-route');
    expect($response)->toBe('Cached Response');

    RouteCache::clear($cachePath);
    expect(file_exists($cachePath))->toBeFalse();
});