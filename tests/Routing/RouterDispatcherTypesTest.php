<?php

use Tyto\Router\Exceptions\ControllerNotFoundException;
use Tyto\Router\Exceptions\InvalidRouteHandlerException;
use Tyto\Router\Routing\Router;

class SampleServiceController
{
    public function handleRequest(): string
    {
        return 'Response from Class Method Array';
    }
}

class ClassicStringController
{
    public function show(): string
    {
        return 'Response from Classic String Format';
    }
    
}

class MockFactoryObject
{
    public function handle(): string
    {
        return 'Response from Factory Object Instance';
    }
}

test('o dipatcher suporta funções anônimas (closures)', function () {
    $routes = [
        'GET' => [
            [
                'controller' => fn () => 'Response from Closure',
                'action' => null,
                'params' => [],
                'pattern' => "#^/closure$#",
                'original' => '/closure',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    $response = $router->dispatch('GET', '/closure');
    expect($response)->toBe('Response from Closure');
});

test('o dispatcher suporta arrays de classes e metodos', function () {
    $routes = [
        'GET' => [
            [
                'controller' => SampleServiceController::class,
                'action' => 'handleRequest',
                'params' => [],
                'pattern' => "#^/array-syntax$#",
                'original' => '/array-syntax',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    $response = $router->dispatch('GET', '/array-syntax');
    expect($response)->toBe('Response from Class Method Array');
});

test('o dispatcher suporta string no formato classico Controller@action', function () {
    $routes = [
        'GET' => [
            [
                'controller' => ClassicStringController::class . '@show',
                'action'     => null,
                'params'     => [],
                'pattern'      => '#^/classic$#',
                'original'   => '/classic',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    $response = $router->dispatch('GET', '/classic');

    expect($response)->toBe('Response from Classic String Format');
});

test('o dispatcher suporta objetos instanciados / factories [Object, method]', function () {
    $factoryInstance = new MockFactoryObject();

    $routes = [
        'GET' => [
            [
                'controller' => [$factoryInstance, 'handle'],
                'action'     => null,
                'params'     => [],
                'pattern'      => '#^/factory$#',
                'original'   => '/factory',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    $response = $router->dispatch('GET', '/factory');

    expect($response)->toBe('Response from Factory Object Instance');
});

test('o router dispara ControllerNotFoundException se o controller nao existir', function () {
    $routes = [
        'GET' => [
            [
                'controller' => 'ControllerInexistente@index',
                'action' => null,
                'params' => [],
                'pattern' => "#^/teste$#",
                'original' => '/teste',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    
    expect(fn () => $router->dispatch('GET', '/teste'))
        ->toThrow(ControllerNotFoundException::class);
});

test('o router dispara exceção se a estrutura do handler for inválida', function () {
    $routes = [
        'GET' => [
            [
                'controller' => ['invalido' => 'formato'], // Estrutura malformada
                'action' => null,
                'params' => [],
                'pattern' => "#^/teste$#",
                'original' => '/teste',
                'middlewares' => []
            ]
        ]
    ];

    $router = new Router($routes);
    
    expect(fn () => $router->dispatch('GET', '/teste'))
        ->toThrow(InvalidRouteHandlerException::class);
});