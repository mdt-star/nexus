<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class CaseMiddlewareTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // 将当前用户设为超级管理员，跳过权限标签检查
        Config::set('nexus.super_admin_id', $this->user->id);
        $this->actingAs($this->user);
    }

    /**
     * 测试默认 snake_case（不传任何风格参数）
     */
    public function test_default_snake_case()
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertOk();
        // UserController::index() 返回扁平集合，每条有 id/name/email
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'email'],
        ]);
    }

    /**
     * 测试 header X-Case: camel — 请求参数 camel→snake，响应 snake→camel
     */
    public function test_header_camel_case()
    {
        $response = $this->withHeader('X-Case', 'camel')
            ->getJson('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'email'],
        ]);

        // 验证具体 key 是 camelCase
        $json = $response->json();
        if (! empty($json[0])) {
            $keys = array_keys($json[0]);
            foreach ($keys as $key) {
                if (str_contains($key, '_')) {
                    $this->fail("Response key [{$key}] should be camelCase");
                }
            }
        }
    }

    /**
     * 测试参数 _case=camel
     */
    public function test_parameter_camel_case()
    {
        $response = $this->getJson('/api/v1/admin/users?_case=camel');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'email'],
        ]);
    }

    /**
     * 测试 POST 请求 body 参数转换（camel → snake）
     */
    public function test_post_body_camel_to_snake()
    {
        $response = $this->withHeader('X-Case', 'camel')
            ->postJson('/api/v1/admin/users', [
                'name' => 'John',
                'email' => 'john@example.com',
                'password' => 'secret123',
            ]);

        // POST 返回 201 Created
        $response->assertCreated();
    }

    /**
     * 测试显式声明 snake 时 key 保持原样
     */
    public function test_explicit_snake_case()
    {
        $response = $this->getJson('/api/v1/admin/users?_case=snake');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'email'],
        ]);
    }

    /**
     * 测试 case 中间件已通过 api mount 注册
     */
    public function test_middleware_registered_in_api_mount()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $matched = false;

        foreach ($routes->getRoutes() as $route) {
            if (str_contains($route->uri(), 'api/v1')) {
                $middlewares = $route->gatherMiddleware();
                if (in_array('case', $middlewares)) {
                    $matched = true;
                    break;
                }
            }
        }

        $this->assertTrue($matched, 'case middleware should be registered on api/v1 routes');
    }
}
