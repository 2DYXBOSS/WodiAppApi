<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    /** Chaque route de l'API doit être décrite dans docs/openapi-meta.php (documentation complète). */
    public function test_every_api_route_is_documented(): void
    {
        $meta = require base_path('docs/openapi-meta.php');
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            if (! Str::startsWith($route->uri(), 'api/v1/') || Str::contains($route->uri(), 'sanctum')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' /'.Str::after($route->uri(), 'api/v1/');
                if (! isset($meta['operations'][$key]['summary'])) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame([], $missing, 'Routes non documentées');
    }
}
