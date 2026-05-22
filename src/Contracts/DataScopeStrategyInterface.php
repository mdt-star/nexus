<?php

namespace MdtStar\Nexus\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * 数据范围策略契约
 *
 * 所有数据范围策略类必须实现此接口。
 * 通过 model_scopes 表注册策略类，运行时动态调用。
 */
interface DataScopeStrategyInterface
{
    /**
     * 应用数据范围约束到查询构建器
     *
     * @param Builder $query 当前查询构建器
     * @param string $modelClass 模型全限定类名
     * @param mixed $subject 授权主体（用户或角色）
     * @return Builder
     */
    public function apply(Builder $query, string $modelClass, mixed $subject): Builder;

    /**
     * 获取该策略支持的模型白名单
     *
     * @return array<string> 模型全限定类名数组
     */
    public function getModelWhitelist(): array;

    /**
     * 获取该策略支持的字段白名单
     *
     * @return array<string> 物理字段名数组
     */
    public function getFieldsWhitelist(): array;
}
