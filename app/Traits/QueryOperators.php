<?php

namespace App\Traits;

use Illuminate\Contracts\Database\Query\Expression;

trait QueryOperators
{
    /**
     * Validate that a user-controlled field name is safe for use in query
     * builder methods (where/orderBy) that take identifiers verbatim. Blocks
     * SQL injection via column-name payloads like `email, (SELECT …)`.
     *
     * Accepts: plain identifier (`email`) or one-level dot notation (`user.email`).
     *
     * @param string $field
     * @return bool
     */
    protected function isValidFieldName(string $field): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $field);
    }

    /**
     * 获取查询运算符映射
     *
     * @param string $operator
     * @return string
     */
    protected function getQueryOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            'eq' => '=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
            'notlike' => 'not like',
            'null' => 'null',
            'notnull' => 'notnull',
            default => 'like'
        };
    }

    /**
     * 获取查询值格式化
     *
     * @param string $operator
     * @param mixed $value
     * @return mixed
     */
    protected function formatQueryValue(string $operator, mixed $value): mixed
    {
        return match (strtolower($operator)) {
            'like', 'notlike' => "%{$value}%",
            'null', 'notnull' => null,
            default => $value
        };
    }

    /**
     * 应用查询条件
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return void
     */
    protected function applyQueryCondition($query, array|Expression|string $field, string $operator, mixed $value): void
    {
        $queryOperator = $this->getQueryOperator($operator);

        if ($queryOperator === 'null') {
            $query->whereNull($field);
        } elseif ($queryOperator === 'notnull') {
            $query->whereNotNull($field);
        } elseif ($queryOperator === 'like') {
            // Cross-DB case-insensitive LIKE: PG → ILIKE, MySQL → LIKE (ci collation)
            $query->whereLike($field, $this->formatQueryValue($operator, $value), false);
        } elseif ($queryOperator === 'not like') {
            $query->whereNotLike($field, $this->formatQueryValue($operator, $value), false);
        } else {
            $query->where($field, $queryOperator, $this->formatQueryValue($operator, $value));
        }
    }
} 