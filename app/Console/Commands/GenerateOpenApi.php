<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Génère la spécification OpenAPI 3.1 (`public/docs/openapi.json`) À PARTIR DU CODE :
 * routes, middlewares (authentification, rôle, permission, limitation), règles des Form Requests,
 * complétés par les schémas de réponse et règles métier de `docs/openapi-meta.php`.
 * La documentation ne peut donc pas diverger des routes réellement exposées.
 */
class GenerateOpenApi extends Command
{
    protected $signature = 'wodi:openapi {--output=public/docs/openapi.json}';

    protected $description = 'Génère la documentation OpenAPI de l’API Maison WODI';

    public function handle(): int
    {
        $meta = require base_path('docs/openapi-meta.php');
        $paths = [];

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (! Str::startsWith($route->uri(), 'api/v1/') || Str::contains($route->uri(), 'sanctum')) {
                continue;
            }
            $path = '/'.Str::after($route->uri(), 'api/v1/');
            $path = rtrim($path, '/') ?: '/';
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$path;
                $paths[$path][strtolower($method)] = $this->operation($route, $method, $path, $meta['operations'][$key] ?? []);
            }
        }
        ksort($paths);

        $spec = [
            'openapi' => '3.1.0',
            'info' => $meta['info'],
            'servers' => [['url' => rtrim(config('app.url'), '/').'/api/v1', 'description' => 'Environnement courant']],
            'tags' => $meta['tags'],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Application mobile : jeton d’accès (`tokens.accessToken`).'],
                    'session' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'maison-wodi-core-session',
                        'description' => 'Back-office : cookie de session HttpOnly + en-tête `X-XSRF-TOKEN` (voir `GET /sanctum/csrf-cookie`).'],
                ],
                'schemas' => $meta['schemas'],
                'responses' => $meta['responses'],
            ],
        ];

        $output = base_path($this->option('output'));
        @mkdir(dirname($output), 0755, true);
        file_put_contents($output, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(sprintf('%d chemins documentés → %s', count($paths), $this->option('output')));
        file_put_contents(base_path('docs/ENDPOINTS.md'), $this->markdown($paths));
        $this->info('Tableau des endpoints → docs/ENDPOINTS.md');

        return self::SUCCESS;
    }

    private function operation(Route $route, string $method, string $path, array $meta): array
    {
        $middleware = $route->gatherMiddleware();
        $auth = in_array('auth:sanctum', $middleware, true);
        $permissions = collect($middleware)->filter(fn ($m) => is_string($m) && Str::startsWith($m, 'permission:'))
            ->map(fn ($m) => Str::after($m, 'permission:'))->values()->all();
        $role = collect($middleware)->first(fn ($m) => is_string($m) && Str::startsWith($m, 'role.is:'));
        $throttle = collect($middleware)->filter(fn ($m) => is_string($m) && Str::startsWith($m, 'throttle:') && $m !== 'throttle:api')
            ->map(fn ($m) => Str::after($m, 'throttle:'))->values()->all();

        $segments = explode('/', trim($path, '/'));
        $tag = $meta['tag'] ?? match ($segments[0]) {
            'auth' => 'Authentification',
            'me' => isset($segments[1]) && in_array($segments[1], ['bookings', 'payments', 'loyalty'], true) ? 'Client' : 'Compte',
            'availability' => 'Disponibilités',
            'pro' => 'Professionnel',
            'admin' => 'Back-office',
            default => 'Public',
        };

        $access = [];
        $access[] = $auth ? 'Authentification requise' : 'Public (sans authentification)';
        if ($role) {
            $access[] = 'rôle `'.Str::after($role, 'role.is:').'`';
        }
        if ($permissions) {
            $access[] = 'permission(s) '.implode(', ', array_map(fn ($p) => "`{$p}`", $permissions));
        }
        if ($throttle) {
            $access[] = 'limitation : '.implode(', ', $throttle);
        }

        $operation = [
            'tags' => [$tag],
            'summary' => $meta['summary'] ?? Str::headline($method.' '.$path),
            'operationId' => Str::camel(strtolower($method).' '.str_replace(['{', '}', '/', '-'], ['', '', ' ', ' '], $path)),
            'description' => trim('**Accès** : '.implode(' · ', $access).".\n\n".($meta['description'] ?? '')),
        ];
        if ($auth) {
            $operation['security'] = [['bearer' => []], ['session' => []]];
        }

        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true,
                'schema' => $name === 'token' || $name === 'action' ? ['type' => 'string'] : ['type' => 'string', 'format' => 'uuid']];
        }
        foreach ($meta['query'] ?? [] as $name => $schema) {
            $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => is_array($schema) ? $schema : ['type' => $schema]];
        }
        if ($parameters) {
            $operation['parameters'] = $parameters;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && ($body = $meta['body'] ?? $this->requestBody($route, $method))) {
            $operation['requestBody'] = ['required' => true, 'content' => [
                ($meta['multipart'] ?? false) ? 'multipart/form-data' : 'application/json' => ['schema' => $body],
            ]];
        }

        $status = (string) ($meta['status'] ?? ($method === 'POST' && ! isset($meta['response']) ? 204 : 200));
        $responses = [$status => $status === '204'
            ? ['description' => 'Succès, sans contenu']
            : ['description' => 'Succès', 'content' => ['application/json' => ['schema' => $meta['response'] ?? ['type' => 'object']]]]];
        $responses['422'] = ['$ref' => '#/components/responses/Validation'];
        if ($auth) {
            $responses['401'] = ['$ref' => '#/components/responses/Unauthenticated'];
            $responses['403'] = ['$ref' => '#/components/responses/Forbidden'];
        }
        if ($route->parameterNames()) {
            $responses['404'] = ['$ref' => '#/components/responses/NotFound'];
        }
        foreach ($meta['errors'] ?? [] as $code => $description) {
            $responses[(string) $code] = ['description' => $description, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
        }
        if ($throttle || ! $auth) {
            $responses['429'] = ['$ref' => '#/components/responses/TooManyRequests'];
        }
        ksort($responses);
        $operation['responses'] = $responses;

        return $operation;
    }

    /** Tableau Markdown des endpoints (généré, ne pas modifier à la main). */
    private function markdown(array $paths): string
    {
        $byTag = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $op) {
                $access = rtrim(Str::before(Str::after($op['description'], '**Accès** : '), "\n"), '.');
                $byTag[$op['tags'][0]][] = sprintf('| `%s` | `%s` | %s | %s |', strtoupper($method), $path, $op['summary'], str_replace('|', '\\|', $access));
            }
        }
        $out = "# Endpoints — Maison WODI Core API (`/api/v1`)\n\n> Généré par `php artisan wodi:openapi` à partir des routes. Détail : `/api/docs` (Swagger UI).\n";
        foreach ($byTag as $tag => $rows) {
            $out .= "\n## {$tag}\n\n| Méthode | Route | Description | Accès |\n|---|---|---|---|\n".implode("\n", $rows)."\n";
        }

        return $out;
    }

    /** Corps de requête déduit des règles du Form Request du contrôleur. */
    private function requestBody(Route $route, string $method): ?array
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            [$class, $fn] = [$action, '__invoke'];
        } else {
            [$class, $fn] = explode('@', $action);
        }
        if (! method_exists($class, $fn)) {
            return null;
        }
        foreach ((new ReflectionMethod($class, $fn))->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                $requestClass = $type->getName();
                /** @var FormRequest $request */
                $request = $requestClass::createFrom(Request::create('/'.$route->uri(), $method));
                $bound = clone $route;
                $bound->parameters = array_fill_keys($route->parameterNames(), null);
                $request->setRouteResolver(fn () => $bound);

                return $this->schemaFromRules($request->rules());
            }
        }

        return null;
    }

    private function schemaFromRules(array $rules): array
    {
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];
        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.')) {
                continue; // champs imbriqués : décrits par le parent
            }
            $list = collect(is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules))->filter(fn ($r) => is_string($r));
            $property = ['type' => 'string'];
            foreach ($list as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                match ($name) {
                    'integer' => $property['type'] = 'integer',
                    'numeric' => $property['type'] = 'number',
                    'boolean' => $property['type'] = 'boolean',
                    'array' => $property = ['type' => 'array', 'items' => ['type' => 'string']] + array_diff_key($property, ['type' => 1]),
                    'file' => $property = ['type' => 'string', 'format' => 'binary'],
                    'email' => $property['format'] = 'email',
                    'uuid' => $property['format'] = 'uuid',
                    'url' => $property['format'] = 'uri',
                    'date_format' => $property['format'] = 'date',
                    'in' => $property['enum'] = explode(',', (string) $arg),
                    'max' => $property[$property['type'] === 'integer' ? 'maximum' : ($property['type'] === 'array' ? 'maxItems' : 'maxLength')] = (int) $arg,
                    'nullable' => $property['nullable'] = true,
                    'regex' => str_contains((string) $arg, '[0-5]\d$') ? $property['pattern'] = '^([01]\d|2[0-3]):[0-5]\d$' : null,
                    default => null,
                };
                if ($name === 'required') {
                    $schema['required'][] = $field;
                }
            }
            if (isset($property['nullable'])) {
                unset($property['nullable']);
                $property['type'] = [$property['type'], 'null'];
            }
            $schema['properties'][$field] = $property;
        }
        if ($schema['required'] === []) {
            unset($schema['required']);
        }

        return $schema;
    }
}
