<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Maison WODI Core API', 'version' => 'v1', 'docs' => config('wodi.docs_enabled') ? url('/api/docs') : null]));

// Documentation OpenAPI (Swagger UI) : activée en développement, désactivée par défaut en production (API_DOCS_ENABLED).
Route::get('/api/docs', function () {
    abort_unless(config('wodi.docs_enabled'), 404);

    return response(<<<'HTML'
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maison WODI Core API</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.17.14/swagger-ui.min.css">
</head>
<body>
  <div id="swagger"></div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.17.14/swagger-ui-bundle.min.js"></script>
  <script>SwaggerUIBundle({ url: '/docs/openapi.json', dom_id: '#swagger', docExpansion: 'none', deepLinking: true });</script>
</body>
</html>
HTML);
});
