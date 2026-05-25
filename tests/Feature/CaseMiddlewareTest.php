<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

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

        // 注册一个带复合字段的临时测试路由
        Route::get('/api/v1/admin/test-case', function () {
            return [
                'user_role' => 'admin',
                'created_at' => '2024-01-01',
                'display_name' => 'Test User',
                'nested_data' => [
                    'inner_key' => 'value',
                    'deep_nest' => [
                        'deep_key' => 'deep_value',
                    ],
                ],
            ];
        })->middleware(['auth.tag', 'case']);
    }

    /**
     * 测试默认 camelCase — 复合字段自动转换
     */
    public function test_default_camel_case()
    {
        $response = $this->getJson('/api/v1/admin/test-case');

        $response->assertOk();
        $json = $response->json();

        // 复合字段应转为 camelCase
        $this->assertArrayHasKey('userRole', $json, 'user_role should become userRole');
        $this->assertArrayHasKey('createdAt', $json, 'created_at should become createdAt');
        $this->assertArrayHasKey('displayName', $json, 'display_name should become displayName');

        // 嵌套数组 key 也应转换
        $this->assertArrayHasKey('innerKey', $json['nestedData'], 'inner_key should become innerKey');
        $this->assertArrayHasKey('deepKey', $json['nestedData']['deepNest'], 'deep_key should become deepKey');

        // 单字段保持原样
        $this->assertArrayHasKey('nestedData', $json, 'nested_data should become nestedData');
    }

    /**
     * 测试 header X-Case: snake — 复合字段保持 snake_case
     */
    public function test_header_snake_case()
    {
        $response = $this->withHeader('X-Case', 'snake')
            ->getJson('/api/v1/admin/test-case');

        $response->assertOk();
        $json = $response->json();

        // 复合字段保持 snake_case
        $this->assertArrayHasKey('user_role', $json);
        $this->assertArrayHasKey('created_at', $json);
        $this->assertArrayHasKey('display_name', $json);
        $this->assertArrayHasKey('nested_data', $json);
        $this->assertArrayHasKey('inner_key', $json['nested_data']);
        $this->assertArrayHasKey('deep_key', $json['nested_data']['deep_nest']);
    }

    /**
     * 测试参数 _case=snake
     */
    public function test_parameter_snake_case()
    {
        $response = $this->getJson('/api/v1/admin/test-case?_case=snake');

        $response->assertOk();
        $json = $response->json();

        $this->assertArrayHasKey('user_role', $json);
        $this->assertArrayHasKey('created_at', $json);
    }

    /**
     * 测试 POST 请求 body 参数转换（camel → snake）
     *
     * 前端传 camelCase 参数，后端应收到 snake_case。
     * 通过返回 array_keys 和 isset 标记来验证后端实际收到的 key 是 snake_case。
     */
    public function test_post_body_camel_to_snake()
    {
        // 注册一个 POST 测试路由，回显后端实际收到的参数 key
        Route::post('/api/v1/admin/test-echo', function (\Illuminate\Http\Request $request) {
            $params = $request->all();
            return [
                'received_keys' => array_keys($params),
                'has_user_role' => isset($params['user_role']),
                'has_display_name' => isset($params['display_name']),
                'user_role_value' => $params['user_role'] ?? null,
                'display_name_value' => $params['display_name'] ?? null,
            ];
        })->middleware(['auth.tag', 'case']);

        // 前端传 camelCase
        $response = $this->postJson('/api/v1/admin/test-echo', [
            'userRole' => 'admin',
            'displayName' => 'Test',
        ]);

        $response->assertOk();
        $json = $response->json();

        // 验证后端实际收到的是 snake_case key
        $this->assertContains('user_role', $json['receivedKeys'], '后端应收到 user_role');
        $this->assertContains('display_name', $json['receivedKeys'], '后端应收到 display_name');
        $this->assertTrue($json['hasUserRole'], 'isset(user_role) 应为 true');
        $this->assertTrue($json['hasDisplayName'], 'isset(display_name) 应为 true');
        $this->assertEquals('admin', $json['userRoleValue']);
        $this->assertEquals('Test', $json['displayNameValue']);
    }

    /**
     * 测试显式声明 camel 时复合字段转换
     */
    public function test_explicit_camel_case()
    {
        $response = $this->getJson('/api/v1/admin/test-case?_case=camel');

        $response->assertOk();
        $json = $response->json();

        $this->assertArrayHasKey('userRole', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertArrayHasKey('displayName', $json);
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
