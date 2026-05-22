<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Casts\TypedValueCast;
use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

/**
 * 分布式动态配置持久化模型
 *
 * 数组存储为 JSON 字符串，通过 type 自省标签反序列化。
 * value 字段使用 TypedValueCast 自动转换类型。
 *
 * @property int $id
 * @property string $key
 * @property mixed $value 自动根据 type 转换为对应 PHP 类型
 * @property string $type boolean|number|json|null|string
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Config extends Model
{
    protected $table = 'configs';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * 类型转换
     *
     * value 字段通过 TypedValueCast 根据 type 字段动态转换：
     * - boolean → bool
     * - number  → int|float
     * - json    → array
     * - null    → null
     * - string  → string（默认）
     *
     * 写入时自动推断类型并序列化。
     */
    protected $casts = [
        'value' => TypedValueCast::class . ':type',
    ];
}
