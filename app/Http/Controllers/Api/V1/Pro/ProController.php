<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Http\Controllers\Controller;
use App\Models\Professional;
use Illuminate\Http\Request;

/** Base de l'espace professionnel : tout est limité au professionnel du compte connecté. */
abstract class ProController extends Controller
{
    protected function me(Request $request): Professional
    {
        return $request->user()->professional;
    }
}
