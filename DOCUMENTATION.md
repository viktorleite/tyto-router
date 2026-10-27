# Documentação Oficial — Tyto Router (v0.0.1)

O **Tyto Router** é uma biblioteca de roteamento para PHP 8.1+ de altíssima performance, projetada para arquiteturas orientadas a domínio corporativo. O seu principal foco é unir uma **experiência de desenvolvimento limpa e visual (DX)** — através de atributos nativos do PHP — com uma **velocidade extrema** e **segurança rigorosa por padrão**.

---

## Sumário

1. [Filosofia do Projeto](#filosofia-do-projeto)
2. [Requisitos do Sistema](#requisitos-do-sistema)
3. [Instalação](#instalação)
4. [Guia de Utilização (Quick Start)](#guia-de-utilização-quick-start)
5. [Funcionalidades Principais](#funcionalidades-principais)
   - [Atributos HTTP (`#[Get]`, `#[Post]`, etc.)](#atributos-http)
   - [Parâmetros Dinâmicos e Nomeados](#parâmetros-dinâmicos-e-nomeados)
   - [Grupos de Rotas e Prefixos (`#[RouteGroup]`)](#grupos-de-rotas-e-prefixos-routegroup)
   - [Middlewares em Padrão Onion / Pipeline (`#[Middleware]`)](#middlewares-em-padrão-onion--pipeline-middleware)
   - [Injeção do Tyto HTTP (`Request` & `Response`)](#injeção-do-tyto-http-request--response)
   - [Resolução Flexível de Handlers](#resolução-flexível-de-handlers)
6. [Segurança e Confiabilidade](#segurança-e-confiabilidade)
   - [Proteção contra ReDOS](#1-proteção-contra-redos-regular-expression-denial-of-service)
   - [Sanitização de URI](#2-sanitização-e-tratamento-de-uri)
   - [Prevenção de Path Traversal](#3-prevenção-de-path-traversal)
7. [Otimização de Performance: Cache de Rotas em Ficheiro](#otimização-de-performance-cache-de-rotas-em-ficheiro)
8. [Tratamento de Exceções Baseado na SPL](#tratamento-de-exceções-baseado-na-spl)

---

## Filosofia do Projeto

Em frameworks tradicionais, a verificação de rotas e anotações utilizando a API de Reflexão (`ReflectionClass`, `getAttributes`) em tempo de execução para cada requisição HTTP degrada severamente a performance.

O Tyto Router resolve isso dividindo o ciclo de vida em dois momentos:

1. **Build / Desenvolvimento:** Os controllers são analisados via Reflection apenas quando necessário (ou na compilação do cache).
2. **Produção:** O array de rotas compilado é lido diretamente de um ficheiro estático em cache, eliminando o overhead de reflexão e garantindo respostas incrivelmente rápidas (abaixo de 50ms).

---

## Requisitos do Sistema

* **PHP:** `^8.1` ou superior
* **Extensões:** `pcre` (suporte a expressões regulares)

---

## Instalação

Adicione o pacote ao seu projeto via Composer (ou inclua-o via repositório local):

```bash
composer require tyto-php/router
```

---

## Guia de Utilização (Quick Start)

### 1. Criando um Controller com Atributos e Injeção do Tyto HTTP

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\Post;
use Tyto\Http\Request;
use Tyto\Http\Response;

final class BookController
{
    #[Get('/books')]
    public function index(): Response
    {
        return Response::json([
            'data' => [
                ['id' => 1, 'title' => 'Clean Code'],
                ['id' => 2, 'title' => 'Domain-Driven Design']
            ]
        ]);
    }

    #[Get('/books/{id}')]
    public function show(string $id): Response
    {
        return Response::json([
            'data' => ['id' => $id, 'title' => "Livro ID: {$id}"]
        ]);
    }

    #[Post('/books')]
    public function store(Request $request): Response
    {
        $title = $request->input('title');

        return Response::json([
            'message' => 'Livro criado com sucesso!',
            'title' => $title
        ], 201);
    }
}
```

### 2. Despachando as Rotas na sua Aplicação (`public/index.php`)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\Router;
use Tyto\Http\Request;
use Tyto\Http\Response;
use App\Controllers\BookController;

// 1. Captura a requisição via Tyto HTTP
$request = Request::createFromGlobals();

// 2. Coleta e compila os controllers
$collector = new RouteCollector();
$collector->addController(BookController::class);
$routes = $collector->compile();

// 3. Instancia o Router com o mapa de rotas
$router = new Router($routes);

try {
    // 4. Executa o despacho passando a URI, Método e a instância da Request
    $response = $router->dispatch($request->getMethod(), $request->getUri(), $request);

    if ($response instanceof Response) {
        $response->send();
    } else {
        echo $response;
    }
} catch (\Exception $e) {
    (new Response($e->getMessage(), 404))->send();
}
```

---

## Funcionalidades Principais

### Atributos HTTP

O Tyto Router suporta os principais verbos HTTP de forma nativa por meio de atributos posicionados diretamente sobre os métodos do controller:

* `#[Get('/caminho')]`
* `#[Post('/caminho')]`
* `#[Put('/caminho')]`
* `#[Patch('/caminho')]`
* `#[Delete('/caminho')]`

### Parâmetros Dinâmicos e Nomeados

Parâmetros dinâmicos são declarados entre chaves `{param}`. Eles são convertidos internamente em grupos de captura nomeados seguros:

```php
#[Get('/categories/{slug}')]
public function showCategory(string $slug): Response
{
    return Response::json(['category' => $slug]);
}
```

### Grupos de Rotas e Prefixos (`#[RouteGroup]`)

Você pode agrupar rotas a nível de classe utilizando o atributo `#[RouteGroup]`. Isso aplica automaticamente um prefixo a todas as rotas daquele controller e herda middlewares globais para o grupo:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\RouteGroup;
use Tyto\Http\Response;
use App\Middlewares\JwtAuthMiddleware;

#[RouteGroup(prefix: '/api/v1', middlewares: [JwtAuthMiddleware::class])]
final class ApiUserController
{
    #[Get('/profile')]
    public function profile(): Response
    {
        return Response::json(['user' => 'Perfil do Usuário Autenticado']);
    }
}
```

### Middlewares em Padrão Onion / Pipeline (`#[Middleware]`)

Middlewares interceptam requisições antes de chegarem ao controller. Eles devem implementar o contrato `MiddlewareInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Middlewares;

use Tyto\Router\Routing\Contracts\MiddlewareInterface;
use Tyto\Http\Request;
use Tyto\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        // Exemplo de pré-processamento verificando token HTTP
        if ($request instanceof Request && !$request->header('Authorization')) {
            return Response::json(['error' => 'Não autorizado'], 401);
        }

        // Passa para o próximo middleware ou controller
        return $next($request);
    }
}
```

Aplicando o middleware a uma rota específica:

```php
#[Get('/admin/dashboard')]
#[Middleware(AuthMiddleware::class)]
public function dashboard(): Response
{
    return Response::json(['dashboard' => 'Painel Administrativo']);
}
```

### Injeção do Tyto HTTP (`Request` & `Response`)

O Tyto Router resolve e injeta automaticamente instâncias de `Tyto\Http\Request` quando declaradas como dependências na assinatura dos métodos dos controllers ou closures.

### Resolução Flexível de Handlers

O método dispatcher do Tyto Router suporta múltiplos tipos de retornos e chamadas:

* **Funções Anônimas (Closures):** `fn (Request $req) => Response::json(['ok' => true])`
* **Arrays de Classes e Métodos:** `[HomeController::class, 'index']`
* **Strings Clássicas:** `'App\Controllers\HomeController@index'`
* **Objetos Instanciados / Factories:** `[new CustomFactory(), 'handle']`

---

## Segurança e Confiabilidade

O Tyto Router possui mecanismos nativos de proteção contra vetores comuns de ataque web:

### 1. Proteção contra ReDOS (Regular Expression Denial of Service)

As expressões regulares geradas dinamicamente para o mapeamento de rotas evitam curingas genéricos e perigosos como `.*`. O framework restringe estritamente o casamento de segmentos utilizando limites seguros, como `([^/]+)`, garantindo alta velocidade e imunidade a travamentos por regex maliciosa.

### 2. Sanitização e Tratamento de URI

Antes de efetuar o matching, o roteador isola o caminho da URL de forma segura com `parse_url`, ignorando query strings indesejadas (`?foo=bar`) e normalizando barras duplicadas (`//`).

### 3. Prevenção de Path Traversal

Parâmetros dinâmicos extraídos da URI passam por decodificação (`urldecode`) e validação estrita. Caso o sistema identifique tentativas de injeção de controle de diretório (como sequências `../`, `..\` ou `..`), uma exceção de segurança é disparada imediatamente:

```php
throw new SecurityViolationException('Security violation: Path traversal sequence detected.');
```

---

## Otimização de Performance: Cache de Rotas em Ficheiro

Para garantir tempos de resposta inferiores a **50ms**, utilize a classe `RouteCache` em ambiente de produção para evitar o uso de Reflexão a cada request.

### Salvando e Carregando o Cache:

```php
use Tyto\Router\Routing\RouteCollector;
use Tyto\Router\Routing\RouteCache;
use Tyto\Router\Routing\Router;
use Tyto\Http\Request;
use Tyto\Http\Response;
use App\Controllers\BookController;

$cacheFile = __DIR__ . '/../storage/cache/routes.php';

// Tenta carregar as rotas do cache em disco
$routes = RouteCache::load($cacheFile);

if ($routes === null) {
    // Se não existir (ambiente de desenvolvimento), compila via Reflection
    $collector = new RouteCollector();
    $collector->addController(BookController::class);
    $routes = $collector->compile();

    // Salva o resultado compilado em um ficheiro PHP otimizado
    RouteCache::store($cacheFile, $routes);
}

$request = Request::createFromGlobals();
$router = new Router($routes);

$response = $router->dispatch($request->getMethod(), $request->getUri(), $request);

if ($response instanceof Response) {
    $response->send();
}
```

---

## Tratamento de Exceções Baseado na SPL

O Tyto Router substitui erros genéricos de PHP por exceções semânticas dedicadas que estendem as classes nativas da SPL (`\Exception`, `\InvalidArgumentException`, etc.):

* `RouteNotFoundException`: Disparada quando nenhuma rota corresponde à URI ou método HTTP informado (404).
* `ControllerNotFoundException`: Disparada quando a classe do controller mapeada não existe.
* `ActionNotFoundInController`: Disparada quando o método/action especificado não existe no controller.
* `MethodNotFoundException`: Disparada quando um método em objeto instanciado não é encontrado.
* `MiddlewareNotFoundException`: Disparada se a classe de middleware referenciada não for encontrada.
* `MiddlewareUnexpectedValueException`: Disparada se o middleware não implementar `MiddlewareInterface`.
* `InvalidRouteHandlerException`: Disparada quando a estrutura do handler fornecida está malformada.
* `SecurityViolationException`: Disparada ao detectar tentativas de Path Traversal.