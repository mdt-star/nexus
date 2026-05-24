<?php

namespace MdtStar\Nexus\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * 可过滤查询 Trait
 *
 * 配合 FilterRequest 使用，将结构化的过滤条件数组转为 SQL 查询。
 * FilterRequest 负责定义"能查什么字段、用什么操作符、默认值是什么"，
 * 本 Trait 只负责执行查询构建，不参与权限控制。
 *
 * 使用方式：
 * ```php
 * ModelAccess::filter($request->filters())->paginate();
 * ```
 *
 * $filters 格式：
 * ```php
 * [
 *     'field' => [
 *         'operator' => 'eq',   // 操作符
 *         'value'    => 'xxx',  // 值
 *     ],
 * ]
 * ```
 *
 * 支持的操作符：
 * - eq / neq          : where field = ? / != ?
 * - like              : where field like ?
 * - gt / gte / lt / lte : where field > / >= / < / <= ?
 * - in / not_in       : where field in (?) / not in (?)  （值用逗号分隔）
 * - between / not_between : where field between ? and ? （值用逗号分隔，两项）
 * - null / not_null   : where field is null / is not null
 */
trait Filterable
{
    /**
     * 过滤查询作用域
     *
     * @param Builder $query
     * @param array $filters 结构化的过滤条件
     * @return Builder
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $condition) {
            $operator = $condition['operator'] ?? 'eq';
            $value = $condition['value'] ?? null;

            if (is_null($value)) {
                continue;
            }

            $this->applyFilter($query, $field, $operator, $value);
        }

        return $query;
    }

    /**
     * 应用单个过滤条件
     *
     * @param Builder $query
     * @param string $field 字段名
     * @param string $operator 操作符
     * @param mixed $value 值
     */
    protected function applyFilter(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq'          => $query->where($field, $value),
            'neq'         => $query->where($field, '!=', $value),
            'like'        => $query->where($field, 'like', str_contains($value, '%') ? $value : '%' . $value . '%'),
            'gt'          => $query->where($field, '>', $value),
            'gte'         => $query->where($field, '>=', $value),
            'lt'          => $query->where($field, '<', $value),
            'lte'         => $query->where($field, '<=', $value),
            'in'          => $query->whereIn($field, explode(',', $value)),
            'not_in'      => $query->whereNotIn($field, explode(',', $value)),
            'between'     => $query->whereBetween($field, explode(',', $value, 2)),
            'not_between' => $query->whereNotBetween($field, explode(',', $value, 2)),
            'null'        => $query->whereNull($field),
            'not_null'    => $query->whereNotNull($field),
            default       => null,
        };
    }
}
