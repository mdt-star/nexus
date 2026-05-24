<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Contracts\HasPermission;
use MdtStar\Nexus\Models\Permissionable;
use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use MdtStar\Nexus\Traits\HasPermissionTrait;

/**
 * HasPermissionTrait 单元测试
 *
 * 覆盖：
 * - getPermissionTags() 缓存逻辑
 * - hasTag() 匹配逻辑（含 package_id）
 * - flushPermissionCache() 清理
 * - permissionTags() 多态关联
 */
class HasPermissionTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /** @test */
    public function 默认实现返回空集合()
    {
        $model = $this->createPermissionModel();

        $tags = $model->getPermissionTags();

        $this->assertCount(0, $tags);
    }

    /** @test */
    public function 返回已授权的tag列表()
    {
        $model = $this->createPermissionModel();
        $package = Package::create(['name' => 'test/package']);

        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'list',
            'package_id' => $package->id,
        ]);
        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'add',
            'package_id' => null,
        ]);

        $tags = $model->getPermissionTags();

        $this->assertCount(2, $tags);

        $listTag = $tags->firstWhere('tag', 'list');
        $this->assertNotNull($listTag);
        $this->assertEquals($package->id, $listTag->package_id);

        $addTag = $tags->firstWhere('tag', 'add');
        $this->assertNotNull($addTag);
        $this->assertNull($addTag->package_id);
    }

    /** @test */
    public function getPermissionTags走缓存()
    {
        $model = $this->createPermissionModel();
        $cacheKey = $model->permissionCacheKey();

        // 首次调用应缓存结果
        $tags = $model->getPermissionTags();
        $this->assertCount(0, $tags);

        // 直接插入数据（绕过模型）
        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'list',
        ]);

        // 缓存未清理，应返回空（走缓存）
        $tags = $model->getPermissionTags();
        $this->assertCount(0, $tags);

        // 清理缓存后应返回新数据
        $model->flushPermissionCache();
        $tags = $model->getPermissionTags();
        $this->assertCount(1, $tags);
        $this->assertEquals('list', $tags[0]->tag);
    }

    /** @test */
    public function hasTag匹配指定tag()
    {
        $model = $this->createPermissionModel();
        $package = Package::create(['name' => 'test/package']);

        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'list',
            'package_id' => $package->id,
        ]);

        $this->assertTrue($model->hasTag('list', $package->id));
        $this->assertFalse($model->hasTag('add', $package->id));
        $this->assertFalse($model->hasTag('list')); // 无 package_id 不匹配
    }

    /** @test */
    public function hasTag匹配全局tag()
    {
        $model = $this->createPermissionModel();

        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'global:tag',
            'package_id' => null,
        ]);

        $this->assertTrue($model->hasTag('global:tag'));
        $this->assertFalse($model->hasTag('global:tag', 999)); // 指定包不匹配
    }

    /** @test */
    public function flushPermissionCache清理缓存()
    {
        $model = $this->createPermissionModel();
        $cacheKey = $model->permissionCacheKey();

        // 触发缓存
        $model->getPermissionTags();
        $this->assertTrue(Cache::has($cacheKey));

        $model->flushPermissionCache();
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function permissionCacheKey格式正确()
    {
        $model = $this->createPermissionModel();

        $key = $model->permissionCacheKey();

        $this->assertStringContainsString(get_class($model), $key);
        $this->assertStringContainsString((string) $model->id, $key);
    }

    /** @test */
    public function permissionTags多态关联正确()
    {
        $model = $this->createPermissionModel();

        Permissionable::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'tag' => 'list',
        ]);

        $this->assertCount(1, $model->permissionTags);
        $this->assertEquals('list', $model->permissionTags->first()->tag);
    }

    /**
     * 创建一个实现 HasPermission 的测试模型
     */
    protected function createPermissionModel(): HasPermission
    {
        $model = new class extends Authenticatable implements HasPermission
        {
            use HasPermissionTrait;

            protected $table = 'test_permission_models';
        };

        // 创建测试表
        \Illuminate\Support\Facades\Schema::create('test_permission_models', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $model->save();

        return $model;
    }
}
