<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Package API 集成测试
 *
 * 覆盖：
 * - 只读列表
 * - 按 name 过滤
 */
class PackageApiTest extends TestCase
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

        Package::create(['name' => 'mdt-star/nexus']);
        Package::create(['name' => 'test/article']);
    }

    /** @test */
    public function 获取包列表()
    {
        $response = $this->getJson('/api/v1/admin/packages');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按名称过滤包()
    {
        $response = $this->getJson('/api/v1/admin/packages?name=nexus');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'mdt-star/nexus']);
    }
}
