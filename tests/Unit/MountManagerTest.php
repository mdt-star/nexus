<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Routing\MountManager;
use MdtStar\Nexus\Routing\MountInstance;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * MountManager 单元测试
 *
 * 测试路由挂载系统的核心功能：
 * - mount 注册与解析
 * - 能力注册与应用
 * - 继承（extends）
 * - 参数传递
 * - 快捷宏
 * - withoutAuth 链式调用
 * - 前缀合并规则
 * - $route 参数传递
 */
class MountManagerTest extends TestCase
{
    protected MountManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new MountManager();
    }

    /** @test */
    public function it_can_register_and_resolve_a_mount()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        $resolved = $this->manager->resolveMount('test');

        $this->assertEquals('/test', $resolved['prefix']);
        $this->assertEquals([], $resolved['abilities']);
    }

    /** @test */
    public function it_can_pass_parameters_to_mount_resolver()
    {
        $this->manager->extend('test', function (string $version = 'v1') {
            return [
                'prefix' => "/api/{$version}",
                'abilities' => [],
            ];
        });

        $resolved = $this->manager->resolveMount('test', ['v2']);

        $this->assertEquals('/api/v2', $resolved['prefix']);
    }

    /** @test */
    public function it_can_inherit_from_parent_mount()
    {
        // 父 mount
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'abilities' => ['auth'],
            ];
        });

        // 子 mount 继承父 mount（相对路径，追加到父级后面）
        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/parent/child', $resolved['prefix']);
        $this->assertEquals(['auth'], $resolved['abilities']);
    }

    /** @test */
    public function it_can_inherit_from_multiple_parents()
    {
        $this->manager->extend('parent1', function () {
            return [
                'prefix' => '/p1',
                'abilities' => ['auth'],
            ];
        });

        $this->manager->extend('parent2', function () {
            return [
                'prefix' => 'p2', // 相对路径，追加到父级后面
                'abilities' => ['audit'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => ['parent1', 'parent2'],
                'prefix' => 'child', // 相对路径，追加
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        // 第一个父级前缀为 base，第二个追加
        $this->assertEquals('/p1/p2/child', $resolved['prefix']);
        $this->assertEquals(['auth', 'audit'], $resolved['abilities']);
    }

    /** @test */
    public function it_can_merge_child_abilities_with_parent()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'abilities' => ['auth', 'audit'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
                'abilities' => ['extra'], // 合并到父级能力中
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        // 子级能力合并到父级，取并集
        $this->assertEquals(['auth', 'audit', 'extra'], $resolved['abilities']);
    }

    /** @test */
    public function it_handles_relative_prefix()
    {
        // 相对路径（不以 / 开头）→ 追加到父级后面
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/api/v1',
                'abilities' => [],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'admin', // 相对路径，追加
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v1/admin', $resolved['prefix']);
    }

    /** @test */
    public function it_handles_absolute_prefix()
    {
        // 绝对路径（以 / 开头）→ 直接替换父级前缀
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/api/v1',
                'abilities' => [],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => '/api/v2', // 绝对路径，替换父级
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v2', $resolved['prefix']);
    }

    /** @test */
    public function it_can_register_and_apply_abilities()
    {
        $this->manager->extendAbility('test_ability', function ($route) {
            return $route->middleware('test.middleware');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['test_ability'],
            ];
        });

        // 验证能力已注册
        $reflection = new \ReflectionClass($this->manager);
        $abilitiesProperty = $reflection->getProperty('abilities');
        $abilitiesProperty->setAccessible(true);
        $abilities = $abilitiesProperty->getValue($this->manager);

        $this->assertArrayHasKey('test_ability', $abilities);
    }

    /** @test */
    public function it_throws_exception_for_undefined_mount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mount [undefined] is not defined.');

        $this->manager->resolveMount('undefined');
    }

    /** @test */
    public function it_throws_exception_for_undefined_ability()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['undefined_ability'],
            ];
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ability [undefined_ability] is not defined.');

        // 尝试应用能力
        $route = Route::prefix('/test');
        $this->manager->applyAbility($route, 'undefined_ability');
    }

    /** @test */
    public function it_can_parse_spec_correctly()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('parseSpec');
        $method->setAccessible(true);

        // 无参数
        [$name, $params] = $method->invoke($this->manager, 'api');
        $this->assertEquals('api', $name);
        $this->assertEquals([], $params);

        // 单参数
        [$name, $params] = $method->invoke($this->manager, 'api:v2');
        $this->assertEquals('api', $name);
        $this->assertEquals(['v2'], $params);

        // 多参数
        [$name, $params] = $method->invoke($this->manager, 'org:acme-corp,v2');
        $this->assertEquals('org', $name);
        $this->assertEquals(['acme-corp', 'v2'], $params);
    }

    /** @test */
    public function it_can_merge_prefixes()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('mergePrefix');
        $method->setAccessible(true);

        // 绝对路径（以 / 开头）→ 直接替换
        $this->assertEquals('/admin', $method->invoke($this->manager, '/api/v1', '/admin'));
        $this->assertEquals('/api/v2', $method->invoke($this->manager, '/api/v1', '/api/v2'));

        // 相对路径（不以 / 开头）→ 追加到父级后面
        $this->assertEquals('/api/v1/admin', $method->invoke($this->manager, '/api/v1', 'admin'));
        $this->assertEquals('/api/v1', $method->invoke($this->manager, '', 'api/v1'));
    }

    /** @test */
    public function it_can_detect_absolute_prefix()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('isAbsolutePrefix');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->manager, '/api'));
        $this->assertTrue($method->invoke($this->manager, '/admin'));
        $this->assertFalse($method->invoke($this->manager, 'api'));
        $this->assertFalse($method->invoke($this->manager, 'admin/users'));
    }

    /** @test */
    public function it_registers_shortcut_macro()
    {
        // 注册 mount
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // 验证快捷宏已注册
        $this->assertTrue(Route::hasMacro('test'));
    }

    /** @test */
    public function shortcut_macro_can_be_called_with_callback_receiving_route()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        $routeReceived = null;

        // 通过快捷宏调用，回调接收 $route 参数
        Route::test(function ($route) use (&$routeReceived) {
            $routeReceived = $route;
        });

        $this->assertInstanceOf(MountInstance::class, $routeReceived);
    }

    /** @test */
    public function shortcut_macro_can_be_called_with_params_and_callback()
    {
        $this->manager->extend('test', function (string $version = 'v1') {
            return [
                'prefix' => "/test/{$version}",
                'abilities' => [],
            ];
        });

        $routeReceived = null;

        Route::test('v2', function ($route) use (&$routeReceived) {
            $routeReceived = $route;
        });

        $this->assertInstanceOf(MountInstance::class, $routeReceived);
    }

    /** @test */
    public function it_can_create_mount_instance_without_auth()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        $instance = $this->manager->instance('test');

        $this->assertInstanceOf(MountInstance::class, $instance);
    }

    /** @test */
    public function mount_instance_without_auth_adds_to_without_list()
    {
        // 注册 auth 能力
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        $instance = $this->manager->instance('test')->withoutAuth();

        // 通过反射检查 $without 属性，验证 withoutAuth() 将 auth 加入了取消列表
        $reflection = new \ReflectionClass($instance);
        $property = $reflection->getProperty('without');
        $property->setAccessible(true);
        $without = $property->getValue($instance);

        $this->assertArrayHasKey('auth', $without);
        $this->assertTrue($without['auth']);
    }

    /** @test */
    public function mount_instance_without_any_ability_adds_to_without_list()
    {
        // 注册多个能力
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });
        $this->manager->extendAbility('audit', function ($route) {
            return $route->middleware('audit.log');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth', 'audit'],
            ];
        });

        // 通过 __call 动态取消 audit 能力
        $instance = $this->manager->instance('test')->withoutAudit();

        // 验证 audit 被加入取消列表
        $reflection = new \ReflectionClass($instance);
        $property = $reflection->getProperty('without');
        $property->setAccessible(true);
        $without = $property->getValue($instance);

        $this->assertArrayHasKey('audit', $without);
        $this->assertTrue($without['audit']);
        // auth 不在取消列表中
        $this->assertArrayNotHasKey('auth', $without);
    }

    /** @test */
    public function mount_instance_undefined_ability_forwards_to_route_registrar()
    {
        // 只注册 auth，不注册 audit
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        // withoutAudit 未注册，会转交给 RouteRegistrar
        // RouteRegistrar 没有 withoutAudit 方法，所以抛异常
        $this->expectException(\BadMethodCallException::class);

        $this->manager->instance('test')->withoutAudit();
    }

    /** @test */
    public function mount_without_ability_skips_that_ability_in_resolved_config()
    {
        // 注册 auth 能力
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        // 直接测试 mount() 方法，传入带 without 的配置
        // 通过 resolveMount 验证 without 被正确传递
        $this->manager->extend('test_without_auth', function () {
            return [
                'extends' => 'test',
                'prefix' => 'test',
                'without' => ['auth'],
            ];
        });

        $resolved = $this->manager->resolveMount('test_without_auth');

        $this->assertEquals(['auth'], $resolved['abilities']);
        $this->assertEquals(['auth'], $resolved['without']);
    }

    /** @test */
    public function mount_instance_forwards_non_without_methods_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // 非 without 方法会转交给 RouteRegistrar
        // RouteRegistrar 没有 foo 方法，所以抛异常
        $this->expectException(\BadMethodCallException::class);

        $this->manager->instance('test')->foo();
    }

    /** @test */
    public function mount_instance_forwards_get_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // get() 是 RouteRegistrar 的合法方法，返回 Route 对象
        $result = $this->manager->instance('test')->get('/hello', function () {
            return 'hello';
        });

        $this->assertInstanceOf(\Illuminate\Routing\Route::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_post_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // post() 是 RouteRegistrar 的合法方法，返回 Route 对象
        $result = $this->manager->instance('test')->post('/submit', function () {
            return 'submitted';
        });

        $this->assertInstanceOf(\Illuminate\Routing\Route::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_middleware_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // middleware() 是 RouteRegistrar 的合法方法，返回 RouteRegistrar
        $result = $this->manager->instance('test')->middleware('auth');

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_name_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // name() 是 RouteRegistrar 的合法方法，返回 RouteRegistrar
        $result = $this->manager->instance('test')->name('test.');

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_group_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        // group() 是 RouteRegistrar 的合法方法，返回 RouteRegistrar
        $result = $this->manager->instance('test')->group(function () {
            // 路由定义
        });

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_instance_chain_get_after_without_auth()
    {
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        // withoutAuth() 后链式调用 get()，返回 Route 对象
        $result = $this->manager->instance('test')->withoutAuth()->get('/public', function () {
            return 'public';
        });

        $this->assertInstanceOf(\Illuminate\Routing\Route::class, $result);
    }

    /** @test */
    public function mount_callback_receives_route_instance()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => [],
            ];
        });

        $routeReceived = null;

        $this->manager->mount('test', function ($route) use (&$routeReceived) {
            $routeReceived = $route;
        });

        $this->assertInstanceOf(MountInstance::class, $routeReceived);
    }

    /** @test */
    public function without_auth_callback_receives_route_instance()
    {
        $this->manager->extendAbility('auth', function ($route) {
            return $route->middleware('auth:api');
        });

        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'abilities' => ['auth'],
            ];
        });

        $routeReceived = null;

        $this->manager->instance('test')->withoutAuth(function ($route) use (&$routeReceived) {
            $routeReceived = $route;
        });

        $this->assertInstanceOf(MountInstance::class, $routeReceived);
    }

    /** @test */
    public function it_can_inherit_with_parameters()
    {
        $this->manager->extend('parent', function (string $version = 'v1') {
            return [
                'prefix' => "/api/{$version}",
                'abilities' => ['auth'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent:v2',
                'prefix' => 'child', // 相对路径，追加
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v2/child', $resolved['prefix']);
    }
}
