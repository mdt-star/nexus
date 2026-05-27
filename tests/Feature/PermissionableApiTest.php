<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permissionable;
use MdtStar\Nexus\Models\Role;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Permissionable API 集成测试
 *
 * 覆盖：
 * - 授予权限（model 使用 "完整类名@ID" 格式）
 * - 权限列表 + 过滤
 * - 撤销权限
 */
class PermissionableApiTest extends TestCase
{
    protected User $user;
    protected User $admin;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        Config::set('nexus.super_admin_id', $this->admin->id);
        $this->actingAs($this->admin);
        $this->package = Package::create(['name' => 'test/article']);
    }

    /** @test */
    public function 授予用户权限()
    {
        $response = $this->postJson('/api/v1/admin/permissionables', [
            'model' => User::class . '@' . $this->user->id,
            'tag' => 'article:list',
            'package_id' => $this->package->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissionables', [
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
        ]);
    }

    /** @test */
    public function 授予角色权限()
    {
        $role = Role::create(['name' => 'editor', 'slug' => 'editor']);

        $response = $this->postJson('/api/v1/admin/permissionables', [
            'model' => Role::class . '@' . $role->id,
            'tag' => 'article:add',
            'package_id' => $this->package->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissionables', [
            'model_type' => Role::class,
            'model_id' => $role->id,
            'tag' => 'article:add',
        ]);
    }

    /** @test */
    public function model格式校验()
    {
        $response = $this->postJson('/api/v1/admin/permissionables', [
            'model' => 'invalid-format',
            'tag' => 'test',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function 获取已授权权限列表()
    {
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
            'package_id' => $this->package->id,
        ]);
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:add',
            'package_id' => $this->package->id,
        ]);

        $response = $this->getJson('/api/v1/admin/permissionables');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按tag过滤已授权权限()
    {
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
            'package_id' => $this->package->id,
        ]);
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:add',
            'package_id' => $this->package->id,
        ]);

        $response = $this->getJson('/api/v1/admin/permissionables?tag=article:list');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['tag' => 'article:list']);
    }

    /** @test */
    public function 撤销权限()
    {
        $perm = Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
            'package_id' => $this->package->id,
        ]);

        $response = $this->deleteJson('/api/v1/admin/permissionables/' . $perm->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('permissionables', ['id' => $perm->id]);
    }
}
