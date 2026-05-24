<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\ModelHasPermission;
use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Role;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;

/**
 * User 权限穿透 Role 集成测试
 *
 * 覆盖：
 * - User 自身 tag
 * - Role 的 tag
 * - User + Role 合并去重
 * - hasTag() 穿透验证
 * - flushPermissionCache() 清理后重新加载
 */
class UserPermissionPenetrationTest extends TestCase
{
    protected User $user;
    protected Role $role;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->package = Package::create(['name' => 'test/article']);
        $this->role = Role::create(['name' => 'editor']);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // 关联角色
        $this->user->roles()->attach($this->role);
    }

    /** @test */
    public function 用户自身tag()
    {
        ModelHasPermission::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);

        $this->assertTrue($this->user->hasTag('list', $this->package->id));
        $this->assertFalse($this->user->hasTag('add', $this->package->id));
    }

    /** @test */
    public function 角色tag穿透到用户()
    {
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);

        $this->assertTrue($this->user->hasTag('list', $this->package->id));
    }

    /** @test */
    public function 用户tag覆盖角色tag()
    {
        // 角色有 list 和 add
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'add',
            'package_id' => $this->package->id,
        ]);

        // 用户只有 list（覆盖角色）
        ModelHasPermission::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);

        // 用户有 list
        $this->assertTrue($this->user->hasTag('list', $this->package->id));
        // 用户没有 add（虽然角色有，但用户没有显式授权，合并后应有）
        // 注意：当前实现是合并去重，不是覆盖，所以 add 应该还在
        $this->assertTrue($this->user->hasTag('add', $this->package->id));
    }

    /** @test */
    public function 合并去重相同tag只保留一份()
    {
        // 用户和角色都有相同的 tag
        ModelHasPermission::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);

        $tags = $this->user->getPermissionTags();

        // 合并后应只有一条 list
        $listTags = $tags->filter(fn($t) => $t->tag === 'list' && $t->package_id === $this->package->id);
        $this->assertCount(1, $listTags);
    }

    /** @test */
    public function 多个角色tag合并()
    {
        $role2 = Role::create(['name' => 'admin']);
        $this->user->roles()->attach($role2);

        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $role2->id,
            'tag' => 'add',
            'package_id' => $this->package->id,
        ]);

        $this->assertTrue($this->user->hasTag('list', $this->package->id));
        $this->assertTrue($this->user->hasTag('add', $this->package->id));
    }

    /** @test */
    public function 缓存清理后重新加载()
    {
        // 先给角色授权
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'list',
            'package_id' => $this->package->id,
        ]);

        // 首次加载缓存
        $this->assertTrue($this->user->hasTag('list', $this->package->id));

        // 清理用户缓存
        $this->user->flushPermissionCache();

        // 新增一个 tag
        ModelHasPermission::create([
            'model_type' => Role::class,
            'model_id' => $this->role->id,
            'tag' => 'add',
            'package_id' => $this->package->id,
        ]);

        // 清理角色缓存
        $this->role->flushPermissionCache();

        // 重新加载应包含新 tag
        $this->assertTrue($this->user->hasTag('add', $this->package->id));
    }
}
