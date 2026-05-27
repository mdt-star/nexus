<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * User API 集成测试
 *
 * 覆盖：
 * - 创建用户（密码自动 Hash）
 * - 用户列表 + 过滤
 * - 更新用户
 * - 删除用户
 */
class UserApiTest extends TestCase
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
    public function 创建用户()
    {
        $response = $this->postJson('/api/v1/admin/users', [
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => '张三', 'email' => 'zhangsan@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'zhangsan@example.com']);

        // 密码应被 Hash
        $user = User::where('email', 'zhangsan@example.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    /** @test */
    public function 创建用户邮箱唯一校验()
    {
        User::create(['name' => '已有', 'email' => 'dup@test.com', 'password' => bcrypt('p')]);

        $response = $this->postJson('/api/v1/admin/users', [
            'name' => '重复',
            'email' => 'dup@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function 获取用户列表()
    {
        User::create(['name' => '用户A', 'email' => 'a@test.com', 'password' => bcrypt('p')]);
        User::create(['name' => '用户B', 'email' => 'b@test.com', 'password' => bcrypt('p')]);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(200);
        $response->assertJsonCount(3); // admin + 2 users
    }

    /** @test */
    public function 按名称模糊搜索用户()
    {
        User::create(['name' => '张三', 'email' => 'zs@test.com', 'password' => bcrypt('p')]);
        User::create(['name' => '李四', 'email' => 'ls@test.com', 'password' => bcrypt('p')]);

        $response = $this->getJson('/api/v1/admin/users?name=张');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => '张三']);
    }

    /** @test */
    public function 更新用户()
    {
        $user = User::create(['name' => '旧名', 'email' => 'old@test.com', 'password' => bcrypt('p')]);

        $response = $this->putJson('/api/v1/admin/users/' . $user->id, [
            'name' => '新名',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '新名']);
    }

    /** @test */
    public function 更新用户密码()
    {
        $user = User::create(['name' => '测试', 'email' => 'test@test.com', 'password' => bcrypt('old_pwd')]);

        $response = $this->putJson('/api/v1/admin/users/' . $user->id, [
            'password' => 'new_password',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue(password_verify('new_password', $user->password));
    }

    /** @test */
    public function 删除用户()
    {
        $user = User::create(['name' => '待删除', 'email' => 'del@test.com', 'password' => bcrypt('p')]);

        $response = $this->deleteJson('/api/v1/admin/users/' . $user->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
