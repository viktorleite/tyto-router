
# Documentação Oficial — Tyto Router (v0.0.1)

O **Tyto Router** é uma biblioteca de roteamento para PHP 8.1+ de altíssima performance, projetada para arquiteturas orientadas a domínio corporativo. O seu principal foco é unir uma **experiência de desenvolvimento limpa e visual (DX)** — através de atributos nativos do PHP — com uma **velocidade extrema** e **segurança rigorosa por padrão**.

---

## Sumário

1. [Filosofia do Projeto]()
2. [Requisitos do Sistema]()
3. [Instalação]()
4. [Guia de Utilização (Quick Start)]()
5. [Funcionalidades Principais]()
* [Atributos HTTP (`#[Get]`, `#[Post]`, etc.)]()
* [Parâmetros Dinâmicos e Nomeados]()
* [Grupos de Rotas e Prefixos (`#[RouteGroup]`)]()
* [Middlewares em Padrão Onion / Pipeline (`#[Middleware]`)]()
* [Resolução Flexível de Handlers]()


6. [Segurança e Confiabilidade]()
* [Proteção contra ReDOS]()
* [Sanitização de URI]()
* [Prevenção de Path Traversal]()


7. [Otimização de Performance: Cache de Rotas em Ficheiro]()
8. [Tratamento de Exceções Baseado na SPL]()

---

## Filosofia do Projeto

Em frameworks tradicionais, a verificação de rotas e anotações utilizando a API de Reflexão (`ReflectionClass`, `getAttributes`) em tempo de execução para cada requisição HTTP degrada severamente a performance.

O Tyto Router resolve isso dividindo o ciclo de vida em dois momentos:

1. **Build / Desenvolvimento:** Os controllers são analisados via Reflection apenas quando necessário (ou na compilação do cache).
2. **Produção:** O array de rotas compilado é lido diretamente de um ficheiro estático em cache, eliminando o overhead de reflexão e garantindo respostas incrivelmente rápidas.

---

## Requisitos do Sistema

* **PHP:** `^8.1` ou superior
* **Extensões:** `pcre` (suporte a expressões regulares)

---

## Instalação

Adicione o pacote ao seu projeto via Composer (ou inclua-o localmente):

```bash
composer require tyto/router

```

---

## Guia de Utilização (Quick Start)

### 1. Criando um Controller com Atributos

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Tyto\Router\Routing\Attributes\Get;
use Tyto\Router\Routing\Attributes\Post;

final class BookController
{
    #[Get('/books')]
    public function index(): string
    {
        return 'Listagem de Livros';
    }

    #[Get('/books/{id}')]
    public function show(string $id): string
    {
        return "Detalhes do Livro ID: {$id}";
    }

    #[Post('/books')]
    public function store(): string
    {
        return 'Livro criado com sucesso!';
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
use App\Controllers\BookController;

// 1. Coleta e compila os controllers
$collector = new RouteCollector();
$collector->addController(BookController::class);
$routes = $collector->compile();

// 2. Instancia o Router com o mapa de rotas
$router = new Router($routes);

try {
    // 3. Executa o despacho com base no método HTTP e URI atual
    $response = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    echo $response;
} catch (\Exception $e) {
    http_response_code(404);
    echo $e->getMessage();
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
#[Get('/users/{userId}/posts/{postId slicer}')] // Exemplo de parâmetro dinâmico
#[Get('/categories/{slug}')]
public function showCategory(string $slug): string
{
    return "Categoria: {$slug}";
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
use App\Middlewares\JwtAuthMiddleware;

#[RouteGroup(prefix: '/api/v1', middlewares: [JwtAuthMiddleware::class])]
final class ApiUserController
{
    #[Get('/profile')]
    public function profile(): string
    {
        return 'Perfil do Usuário Autenticado';
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

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(mixed $request, callable $next): mixed
    {
        // Lógica de pré-processamento
        if (!$request) {
            return 'Acesso Negado';
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
public function dashboard(): string
{
    return 'Painel Administrativo';
}

```

### Resolução Flexível de Handlers

O método dispatcher do Tyto Router suporta múltiplos tipos de retornos e chamadas:

* **Funções Anônimas (Closures):** `fn () => 'Olá'`
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

$router = new Router($routes);
$response = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

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