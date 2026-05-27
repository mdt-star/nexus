<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\Role;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Role API 集成测试
 *
 * 覆盖：
 * - 角色 CRUD
 * - slug 唯一校验
 * - 删除角色时自动解除用户关联
 */
class RoleApiTest extends TestCase
{
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
    }

    /** @test */
    public function 创建角色()
    {
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => '编辑',
            'slug' => 'editor',
            'description' => '内容编辑人员',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', ['slug' => 'editor', 'name' => '编辑']);
    }

    /** @test */
    public function 创建角色slug唯一校验()
    {
        Role::create(['name' => '已有', 'slug' => 'admin']);

        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => '重复',
            'slug' => 'admin',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function 获取角色列表()
    {
        Role::create(['name' => '管理员', 'slug' => 'admin']);
        Role::create(['name' => '编辑', 'slug' => 'editor']);

        $response = $this->getJson('/api/v1/admin/roles');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按slug过滤角色()
    {
        Role::create(['name' => '管理员', 'slug' => 'admin']);
        Role::create(['name' => '编辑', 'slug' => 'editor']);

        $response = $this->getJson('/api/v1/admin/roles?slug=admin');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['slug' => 'admin']);
    }

    /** @test */
    public function 更新角色()
    {
        $role = Role::create(['name' => '旧名', 'slug' => 'old']);

        $response = $this->putJson('/api/v1/admin/roles/' . $role->id, [
            'name' => '新名',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => '新名']);
    }

    /** @test */
    public function 删除角色自动解除用户关联()
    {
        $role = Role::create(['name' => '待删除', 'slug' => 'del']);
        $user = User::create(['name' => '用户', 'email' => 'u@test.com', 'password' => bcrypt('p')]);
        $user->roles()->attach($role);

        $response = $this->deleteJson('/api/v1/admin/roles/' . $role->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_user', ['role_id' => $role->id]);
    }
}
