<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Pagination du back-office : `?page&pageSize&sort&dir` → `{ items, total, page, pageSize }`.
 */
final class Page
{
    public const MAX_PAGE_SIZE = 1000;

    /**
     * @param  array<string,string|callable>  $sortable  clé front → colonne SQL (ou callback(Builder, dir))
     * @param  callable  $map  modèle → tableau JSON
     */
    public static function make(Builder $query, Request $request, callable $map, array $sortable = [], ?callable $defaultSort = null): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query('pageSize', 10)));
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sort = (string) $request->query('sort', '');

        if ($sort !== '' && isset($sortable[$sort])) {
            $column = $sortable[$sort];
            is_callable($column) ? $column($query, $dir) : $query->orderBy($column, $dir);
        } elseif ($defaultSort) {
            $defaultSort($query);
        }

        $total = (clone $query)->toBase()->getCountForPagination();
        $items = $query->forPage($page, $pageSize)->get();

        return [
            'items' => $items->map($map)->values()->all(),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    /** Recherche insensible à la casse et aux accents simples sur plusieurs colonnes. */
    public static function search(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'ilike', $like);
            }
        });
    }

    public static function bool(Request $request, string $key): ?bool
    {
        $value = $request->query($key);
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
