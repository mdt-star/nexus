<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\ModelScope;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * ModelScope API 集成测试
 *
 * 覆盖：
 * - 只读列表
 * - 按 key/class 过滤
 */
class ModelScopeApiTest extends TestCase
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

        ModelScope::create([
            'key' => 'org_only',
            'class' => 'App\\Scopes\\OrgScope',
            'model_whitelist' => null,
            'fields_whitelist' => ['*'],
        ]);
        ModelScope::create([
            'key' => 'self_only',
            'class' => 'App\\Scopes\\SelfScope',
            'model_whitelist' => ['App\\Models\\Article'],
            'fields_whitelist' => ['*'],
        ]);
    }

    /** @test */
    public function 获取策略列表()
    {
        $response = $this->getJson('/api/v1/admin/model-scopes');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按key过滤策略()
    {
        $response = $this->getJson('/api/v1/admin/model-scopes?key=org_only');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['key' => 'org_only']);
    }

    /** @test */
    public function 按class过滤策略()
    {
        $response = $this->getJson('/api/v1/admin/model-scopes?class=SelfScope');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['key' => 'self_only']);
    }
}
