<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 可过滤请求抽象基类
 *
 * 子类通过 filterRules() 定义"能查什么字段、用什么操作符、默认值是什么"，
 * filters() 方法将请求参数解析为 Filterable Trait 需要的结构化格式。
 *
 * 职责：
 * - 定义可过滤字段白名单（filterRules）
 * - 验证请求参数格式（rules）
 * - 控制谁能访问（authorize）
 * - 提供默认值（default）
 *
 * 使用方式：
 * ```php
 * class ModelAccessFilterRequest extends FilterRequest
 * {
 *     protected function filterRules(): array
 *     {
 *         return [
 *             'subject_type' => [
 *                 'operator' => 'eq',
 *                 'rules'    => 'nullable|string',
 *             ],
 *             'class' => [
 *                 'operator' => 'like',
 *                 'rules'    => 'nullable|string',
 *                 'default'  => '%',  // 默认查所有
 *             ],
 *         ];
 *     }
 * }
 * ```
 */
abstract class FilterRequest extends FormRequest
{
    /**
     * 返回过滤规则
     *
     * 格式：
     * ```php
     * [
     *     'field_name' => [
     *         'operator' => 'eq',              // 操作符（必填）
     *         'rules'    => 'nullable|string', // Laravel 验证规则（必填）
     *         'default'  => null,              // 默认值（可选，null=不设默认）
     *     ],
     * ]
     * ```
     *
     * @return array
     */
    abstract protected function filterRules(): array;

    /**
     * 解析请求参数为 Filterable Trait 需要的结构化格式
     *
     * 处理逻辑：
     * 1. 请求中传了该参数 → 用请求值
     * 2. 没传但有 default → 用默认值
     * 3. 没传也没 default → 跳过该字段
     *
     * @return array 格式: ['field' => ['operator' => 'eq', 'value' => 'xxx']]
     */
    public function filters(): array
    {
        $result = [];

        foreach ($this->filterRules() as $field => $config) {
            $operator = $config['operator'] ?? 'eq';
            $default  = $config['default'] ?? null;

            // 请求中传了该参数 → 用请求值
            if ($this->has($field)) {
                $value = $this->input($field);
            }
            // 没传但有默认值 → 用默认值
            elseif ($default !== null) {
                $value = $default;
            }
            // 没传也没默认值 → 跳过
            else {
                continue;
            }

            $result[$field] = [
                'operator' => $operator,
                'value'    => $value,
            ];
        }

        return $result;
    }

    /**
     * 自动根据 filterRules 生成 Laravel 验证规则
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->filterRules() as $field => $config) {
            $rules[$field] = $config['rules'] ?? 'nullable|string';
        }

        return $rules;
    }
}
