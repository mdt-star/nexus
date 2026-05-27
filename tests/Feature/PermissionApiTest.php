<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permission;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Permission API 集成测试
 *
 * 覆盖：
 * - 权限 CRUD
 * - 树形结构（parent_id）
 * - 按 package_id 过滤
 */
class PermissionApiTest extends TestCase
{
    protected Package $package;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        Config::set('nexus.super_admin_id', $this->admin->id);
        $this->actingAs($this->admin);
        $this->package = Package::create(['name' => 'test/article']);
    }

    /** @test */
    public function 创建权限()
    {
        $response = $this->postJson('/api/v1/admin/permissions', [
            'tag' => 'article:list',
            'package_id' => $this->package->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissions', ['tag' => 'article:list']);
    }

    /** @test */
    public function 创建子权限()
    {
        $parent = Permission::create(['tag' => 'article', 'package_id' => $this->package->id]);

        $response = $this->postJson('/api/v1/admin/permissions', [
            'tag' => 'list',
            'package_id' => $this->package->id,
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissions', ['tag' => 'list', 'parent_id' => $parent->id]);
    }

    /** @test */
    public function 获取权限列表()
    {
        Permission::create(['tag' => 'article:list', 'package_id' => $this->package->id]);
        Permission::create(['tag' => 'article:add', 'package_id' => $this->package->id]);

        $response = $this->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按package_id过滤权限()
    {
        $pkg2 = Package::create(['name' => 'test/user']);
        Permission::create(['tag' => 'article:list', 'package_id' => $this->package->id]);
        Permission::create(['tag' => 'user:list', 'package_id' => $pkg2->id]);

        $response = $this->getJson('/api/v1/admin/permissions?package_id=' . $this->package->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['tag' => 'article:list']);
    }

    /** @test */
    public function 更新权限()
    {
        $perm = Permission::create(['tag' => 'old:tag', 'package_id' => $this->package->id]);

        $response = $this->putJson('/api/v1/admin/permissions/' . $perm->id, [
            'tag' => 'new:tag',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('permissions', ['id' => $perm->id, 'tag' => 'new:tag']);
    }

    /** @test */
    public function 删除权限()
    {
        $perm = Permission::create(['tag' => 'to:delete', 'package_id' => $this->package->id]);

        $response = $this->deleteJson('/api/v1/admin/permissions/' . $perm->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }
}
