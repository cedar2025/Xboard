<?php

namespace App\Traits;

trait QueryOperators
{
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
    protected function applyQueryCondition($query, string $field, string $operator, mixed $value): void
    {
        $queryOperator = $this->getQueryOperator($operator);
        
        if ($queryOperator === 'null') {
            $query->whereNull($field);
        } elseif ($queryOperator === 'notnull') {
            $query->whereNotNull($field);
        } else {
            $query->where($field, $queryOperator, $this->formatQueryValue($operator, $value));
        }
    }
} 