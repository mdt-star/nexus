<?php

namespace MdtStar\Nexus\Scopes;

use MdtStar\Nexus\Contracts\HasModelAccess;
use MdtStar\Nexus\Exceptions\PermissionDeniedException;
use MdtStar\Nexus\Models\ModelScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 数据范围 Scope Trait
 *
 * 注入到模型中，自动添加数据范围查询条件。
 * 通过作用主体（User/Role/Team 等）的 getModelAccess() 方法获取模型访问权限，
 * 自动处理读写删权限熔断和数据范围策略。
 *
 * 跳过作用域：
 * ```php
 * Article::withoutDataScope()->get();
 * ```
 *
 * 注入其他作用主体（作用在变量上，仅本次查询生效）：
 * ```php
 * Article::withSubject($someRole)->get();
 * ```
 *
 * 注入其他作用主体（作用在实例上，实例生命周期内生效）：
 * ```php
 * $article = new Article();
 * $article->setScopeSubject($someRole)->get();
 * ```
 */
trait HasDataScope
{
    /**
     * 当前作用域作用主体（模型实例级别）
     * 默认为 null，通过 setScopeSubject() 注入
     */
    protected ?HasModelAccess $scopeSubject = null;

    /**
     * 启动 Trait
     */
    public static function bootHasDataScope(): void
    {
        // 全局作用域 - 自动注入数据范围条件
        static::addGlobalScope('data_scope', function (Builder $builder) {
            $instance = new static();

            if (! $instance->shouldApplyDataScope($builder)) {
                return;
            }

            $subject = $instance->resolveSubject($builder);
            $instance->applyDataScope($builder, $subject);
        });

        // 创建前检查写入权限
        static::creating(function ($model) {
            return (new static())->checkWritePermission();
        });

        // 更新前检查写入权限
        static::updating(function ($model) {
            return (new static())->checkWritePermission();
        });

        // 删除前检查删除权限
        static::deleting(function ($model) {
            return (new static())->checkDeletePermission();
        });
    }

    /**
     * 设置模型实例的作用主体
     *
     * 对当前实例上的所有查询生效，直到实例销毁或重新设置。
     *
     * @param HasModelAccess $subject 作用主体对象（User/Role/Team 等）
     * @return $this
     */
    public function setScopeSubject(HasModelAccess $subject): static
    {
        $this->scopeSubject = $subject;

        return $this;
    }

    /**
     * 设置当前查询的作用主体（仅本次查询生效）
     *
     * 作用在 Builder 变量上，查询结束变量销毁后自动释放。
     *
     * @param Builder $query
     * @param HasModelAccess $subject 作用主体对象
     * @return Builder
     */
    public function scopeWithSubject(Builder $query, HasModelAccess $subject): Builder
    {
        return $query->setSubject($subject);
    }

    /**
     * 解析当前作用主体
     *
     * 优先级：
     * 1. Builder 上的 subject（withSubject 注入，作用在变量上）
     * 2. 模型实例上的 scopeSubject（setScopeSubject 注入，作用在实例上）
     * 3. Auth::user()（默认兜底）
     *
     * @param Builder|null $builder 当前查询构建器
     * @return HasModelAccess|null
     */
    protected function resolveSubject(?Builder $builder = null): ?HasModelAccess
    {
        // 优先从 Builder 上取（作用在变量上，用完即弃）
        if ($builder !== null && method_exists($builder, 'hasSubject') && $builder->hasSubject()) {
            return $builder->getSubject();
        }

        // 其次从模型实例上取（setScopeSubject 注入）
        // 最后从 Auth::user() 取
        return $this->scopeSubject ?? Auth::user();
    }

    /**
     * 跳过数据范围作用域（查询作用域）
     *
     * 调用此方法后，当前查询将不再受数据范围限制。
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithoutDataScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('data_scope');
    }

    /**
     * 判断是否应应用数据范围
     *
     * @param Builder|null $builder 当前查询构建器
     */
    protected function shouldApplyDataScope(?Builder $builder = null): bool
    {
        if (! config('nexus.data_scope.enabled', true)) {
            return false;
        }

        $subject = $this->resolveSubject($builder);

        if ($subject === null) {
            return false;
        }

        // 超级管理员跳过数据范围限制
        if (method_exists($subject, 'isSuperAdmin') && $subject->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * 应用数据范围条件
     *
     * @param Builder $builder 当前查询构建器
     * @param HasModelAccess $subject 作用主体
     *
     * @throws PermissionDeniedException 当读取权限被拒绝时
     */
    protected function applyDataScope(Builder $builder, HasModelAccess $subject): void
    {
        $accesses = $subject->getModelAccess(static::class);

        foreach ($accesses as $access) {
            // 读取权限熔断
            if (! $access->can_read) {
                throw new PermissionDeniedException('no_read_permission');
            }

            // 应用数据范围策略
            if ($access->scope_key) {
                $this->applyScopeStrategy($builder, $access);
            }
        }
    }

    /**
     * 应用数据范围策略
     *
     * @param Builder $builder 当前查询构建器
     * @param mixed $access 模型访问权限记录
     *
     * @throws PermissionDeniedException 当策略类不存在或策略执行异常时
     */
    protected function applyScopeStrategy(Builder $builder, $access): void
    {
        $scope = ModelScope::where('key', $access->scope_key)->first();

        if (! $scope) {
            throw new PermissionDeniedException('scope_not_found');
        }

        // 检查模型是否在白名单中（null 表示不限制模型）
        $modelClass = static::class;

        if ($scope->model_whitelist !== null && ! in_array($modelClass, $scope->model_whitelist)) {
            throw new PermissionDeniedException('scope_model_not_in_whitelist');
        }

        // 实例化策略类并应用
        $strategyClass = $scope->class;

        if (! class_exists($strategyClass)) {
            throw new PermissionDeniedException('scope_class_not_found');
        }

        try {
            $strategy = app($strategyClass);
            $subject = $this->resolveSubject($builder);
            $strategy->apply($builder, $modelClass, $subject);
        } catch (\Throwable $e) {
            throw new PermissionDeniedException('scope_execution_failed');
        }
    }

    /**
     * 检查写入权限
     *
     * 写操作只检查 can_write 布尔值，不应用 scope 策略。
     * scope 策略仅作用于读操作（查询），写/删操作的数据范围限制
     * 应在业务层通过查询验证实现。
     *
     * @throws PermissionDeniedException 当写入权限被拒绝时
     */
    protected function checkWritePermission(): bool
    {
        $subject = $this->resolveSubject();

        if ($subject === null) {
            return true;
        }

        $accesses = $subject->getModelAccess(static::class);
        $matched = $accesses->first();

        if ($matched && ! $matched->can_write) {
            throw new PermissionDeniedException('no_write_permission');
        }

        return true;
    }

    /**
     * 检查删除权限
     *
     * 删除操作只检查 can_delete 布尔值，不应用 scope 策略。
     * scope 策略仅作用于读操作（查询），写/删操作的数据范围限制
     * 应在业务层通过查询验证实现。
     *
     * @throws PermissionDeniedException 当删除权限被拒绝时
     */
    protected function checkDeletePermission(): bool
    {
        $subject = $this->resolveSubject();

        if ($subject === null) {
            return true;
        }

        $accesses = $subject->getModelAccess(static::class);
        $matched = $accesses->first();

        if ($matched && ! $matched->can_delete) {
            throw new PermissionDeniedException('no_delete_permission');
        }

        return true;
    }
}
