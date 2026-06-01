<?php

namespace App\Support;

use Illuminate\Http\Request;

class ApiQuery
{
    public static function applyTextSearch($query, Request $request, array $columns): void
    {
        $search = trim((string) $request->query('search'));
        if ($search === '') {
            return;
        }

        $query->where(function ($where) use ($columns, $search) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    public static function paginateSorted($query, Request $request, string $defaultSort, int $perPage = 12, array $allowed = [])
    {
        $sort = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $defaultSort;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)
            ->paginate((int) min(max((int) $request->query('per_page', $perPage), 6), 50))
            ->withQueryString();
    }

    public static function csvSafe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
