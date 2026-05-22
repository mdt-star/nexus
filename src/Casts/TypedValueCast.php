<?php

namespace MdtStar\Nexus\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * 动态类型值转换器
 *
 * 根据 Config 模型的 type 字段自动转换 value 为对应 PHP 类型。
 * 写入时自动推断类型并序列化。
 *
 * 使用方式（在 Model 中声明）：
 * ```php
 * protected $casts = [
 *     'value' => TypedValueCast::class . ':type',
 * ];
 * ```
 *
 * 支持的 type：
 * - boolean → bool
 * - number  → int|float
 * - json    → array
 * - null    → null
 * - string  → string（默认）
 */
class TypedValueCast implements CastsAttributes
{
    /**
     * 从数据库读取时转换
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value 数据库中的原始字符串值
     * @param array $attributes 模型的所有属性（用于读取 type 字段）
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes): mixed
    {
        $type = $attributes['type'] ?? 'string';

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'number'  => $this->castNumber($value),
            'json'    => json_decode($value, true) ?? [],
            'null'    => null,
            default   => (string) $value, // string
        };
    }

    /**
     * 写入数据库时转换
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value 要写入的 PHP 值
     * @param array $attributes 模型的所有属性
     * @return array 要写入数据库的字段键值对
     */
    public function set($model, string $key, $value, array $attributes): array
    {
        if (is_null($value)) {
            return [
                'type' => 'null',
                'value' => null,
            ];
        }

        if (is_bool($value)) {
            return [
                'type' => 'boolean',
                'value' => $value ? 'true' : 'false',
            ];
        }

        if (is_int($value) || is_float($value)) {
            return [
                'type' => 'number',
                'value' => (string) $value,
            ];
        }

        if (is_array($value) || is_object($value)) {
            return [
                'type' => 'json',
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }

        // string 兜底
        return [
            'type' => 'string',
            'value' => (string) $value,
        ];
    }

    /**
     * 安全地将字符串值转为数字
     *
     * - 优先保留浮点精度
     * - 科学计数法（如 "1e5"）正确识别为 float
     * - 非数字字符串返回原值（兜底）
     *
     * @param string $value
     * @return int|float|string
     */
    protected function castNumber(string $value): int|float|string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        // 科学计数法或含小数点 → float
        if (stripos($value, 'e') !== false || str_contains($value, '.')) {
            return (float) $value;
        }

        return (int) $value;
    }
}
