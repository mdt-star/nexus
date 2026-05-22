<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据范围策略字典模型
 *
 * @property int $id
 * @property string $key
 * @property string $class
 * @property array|null $model_whitelist
 * @property array $fields_whitelist
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ModelScope extends Model
{
    protected $table = 'model_scopes';

    protected $fillable = [
        'key',
        'class',
        'model_whitelist',
        'fields_whitelist',
    ];

    protected $casts = [
        'model_whitelist' => 'array',
        'fields_whitelist' => 'array',
    ];

    /**
     * 模型默认值
     * fields_whitelist 默认 ["*"] 表示允许所有字段
     */
    protected $attributes = [
        'fields_whitelist' => '["*"]',
    ];
}
