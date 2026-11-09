<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Slug
{
    /** Slug unique pour un modèle (suffixe -2, -3… si nécessaire). */
    public static function unique(string $model, string $value, ?string $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'element';
        $slug = $base;
        $i = 2;
        $query = fn ($s) => $model::query()->when(method_exists($model, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('slug', $s)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
        while ($query($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
