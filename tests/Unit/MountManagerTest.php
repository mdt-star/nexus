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
 * - 中间件配置
 * - 继承（extends）
 * - 参数传递
 * - 快捷宏
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
                'middlewares' => [],
            ];
        });

        $resolved = $this->manager->resolveMount('test');

        $this->assertEquals('/test', $resolved['prefix']);
        $this->assertEquals([], $resolved['middlewares']);
    }

    /** @test */
    public function it_can_pass_parameters_to_mount_resolver()
    {
        $this->manager->extend('test', function (string $version = 'v1') {
            return [
                'prefix' => "/api/{$version}",
                'middlewares' => [],
            ];
        });

        $resolved = $this->manager->resolveMount('test', ['v2']);

        $this->assertEquals('/api/v2', $resolved['prefix']);
    }

    /** @test */
    public function it_can_inherit_from_parent_mount()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'middlewares' => ['auth.tag'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/parent/child', $resolved['prefix']);
        $this->assertEquals(['auth.tag'], $resolved['middlewares']);
    }

    /** @test */
    public function it_can_inherit_from_multiple_parents()
    {
        $this->manager->extend('parent1', function () {
            return [
                'prefix' => '/p1',
                'middlewares' => ['auth.tag'],
            ];
        });

        $this->manager->extend('parent2', function () {
            return [
                'prefix' => 'p2',
                'middlewares' => ['audit.log'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => ['parent1', 'parent2'],
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/p1/p2/child', $resolved['prefix']);
        $this->assertEquals(['auth.tag', 'audit.log'], $resolved['middlewares']);
    }

    /** @test */
    public function it_can_merge_child_middlewares_with_parent()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'middlewares' => ['auth.tag', 'audit.log'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
                'middlewares' => ['extra.mw'],
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals(['auth.tag', 'audit.log', 'extra.mw'], $resolved['middlewares']);
    }

    /** @test */
    public function it_handles_relative_prefix()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/api/v1',
                'middlewares' => [],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'admin',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v1/admin', $resolved['prefix']);
    }

    /** @test */
    public function it_handles_absolute_prefix()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/api/v1',
                'middlewares' => [],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => '/api/v2',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v2', $resolved['prefix']);
    }

    /** @test */
    public function it_throws_exception_for_undefined_mount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mount [undefined] is not defined.');

        $this->manager->resolveMount('undefined');
    }

    /** @test */
    public function it_can_parse_spec_correctly()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('parseSpec');
        $method->setAccessible(true);

        [$name, $params] = $method->invoke($this->manager, 'api');
        $this->assertEquals('api', $name);
        $this->assertEquals([], $params);

        [$name, $params] = $method->invoke($this->manager, 'api:v2');
        $this->assertEquals('api', $name);
        $this->assertEquals(['v2'], $params);

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

        $this->assertEquals('/admin', $method->invoke($this->manager, '/api/v1', '/admin'));
        $this->assertEquals('/api/v2', $method->invoke($this->manager, '/api/v1', '/api/v2'));
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
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $this->assertTrue(Route::hasMacro('test'));
    }

    /** @test */
    public function shortcut_macro_can_be_called_with_callback_receiving_route()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $routeReceived = null;

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
                'middlewares' => [],
            ];
        });

        $routeReceived = null;

        Route::test('v2', function ($route) use (&$routeReceived) {
            $routeReceived = $route;
        });

        $this->assertInstanceOf(MountInstance::class, $routeReceived);
    }

    /** @test */
    public function it_can_create_mount_instance()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $instance = $this->manager->instance('test');

        $this->assertInstanceOf(MountInstance::class, $instance);
    }

    /** @test */
    public function mount_instance_forwards_get_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

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
                'middlewares' => [],
            ];
        });

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
                'middlewares' => [],
            ];
        });

        $result = $this->manager->instance('test')->middleware('auth');

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_name_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $result = $this->manager->instance('test')->name('test.');

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_instance_forwards_group_to_route_registrar()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $result = $this->manager->instance('test')->group(function () {
            //
        });

        $this->assertInstanceOf(\Illuminate\Routing\RouteRegistrar::class, $result);
    }

    /** @test */
    public function mount_callback_receives_route_instance()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
            ];
        });

        $routeReceived = null;

        $this->manager->mount('test', function ($route) use (&$routeReceived) {
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
                'middlewares' => ['auth.tag'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent:v2',
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals('/api/v2/child', $resolved['prefix']);
        $this->assertEquals(['auth.tag'], $resolved['middlewares']);
    }

    /** @test */
    public function it_resolves_middlewares_correctly()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => ['api', 'auth.tag'],
            ];
        });

        $resolved = $this->manager->resolveMount('test');

        $this->assertEquals(['api', 'auth.tag'], $resolved['middlewares']);
    }

    /** @test */
    public function it_merges_middlewares_from_parent_and_child()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'middlewares' => ['api'],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
                'middlewares' => ['auth.tag'],
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals(['api', 'auth.tag'], $resolved['middlewares']);
    }

    /** @test */
    public function it_can_resolve_defaults_from_mount()
    {
        $this->manager->extend('test', function () {
            return [
                'prefix' => '/test',
                'middlewares' => [],
                'defaults' => [
                    'package_id' => 1,
                    'package_name' => 'test/package',
                ],
            ];
        });

        $resolved = $this->manager->resolveMount('test');

        $this->assertEquals([
            'package_id' => 1,
            'package_name' => 'test/package',
        ], $resolved['defaults']);
    }

    /** @test */
    public function it_can_inherit_defaults_from_parent()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'middlewares' => [],
                'defaults' => [
                    'package_id' => 1,
                    'package_name' => 'parent/pkg',
                ],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals([
            'package_id' => 1,
            'package_name' => 'parent/pkg',
        ], $resolved['defaults']);
    }

    /** @test */
    public function child_defaults_overrides_parent_defaults()
    {
        $this->manager->extend('parent', function () {
            return [
                'prefix' => '/parent',
                'middlewares' => [],
                'defaults' => [
                    'package_id' => 1,
                    'package_name' => 'parent/pkg',
                ],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => 'parent',
                'prefix' => 'child',
                'defaults' => [
                    'package_name' => 'child/pkg',
                ],
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        // package_id 继承自父级，package_name 被子级覆盖
        $this->assertEquals([
            'package_id' => 1,
            'package_name' => 'child/pkg',
        ], $resolved['defaults']);
    }

    /** @test */
    public function it_can_inherit_defaults_through_multiple_levels()
    {
        $this->manager->extend('auth', function () {
            return [
                'middlewares' => ['auth.tag'],
                'defaults' => [
                    'package_id' => null,
                    'package_name' => null,
                ],
            ];
        });

        $this->manager->extend('api', function (string $version = 'v1') {
            return [
                'extends' => 'auth',
                'prefix' => "/api/{$version}",
                'middlewares' => ['api'],
            ];
        });

        $this->manager->extend('admin', function (string $version = 'v1') {
            return [
                'extends' => "api:{$version}",
                'prefix' => 'admin',
            ];
        });

        $resolved = $this->manager->resolveMount('admin');

        $this->assertEquals('/api/v1/admin', $resolved['prefix']);
        $this->assertEquals(['auth.tag', 'api'], $resolved['middlewares']);
        $this->assertEquals([
            'package_id' => null,
            'package_name' => null,
        ], $resolved['defaults']);
    }

    /** @test */
    public function it_merges_defaults_from_multiple_parents()
    {
        $this->manager->extend('parent1', function () {
            return [
                'prefix' => '/p1',
                'middlewares' => [],
                'defaults' => [
                    'key1' => 'from-p1',
                ],
            ];
        });

        $this->manager->extend('parent2', function () {
            return [
                'prefix' => 'p2',
                'middlewares' => [],
                'defaults' => [
                    'key2' => 'from-p2',
                ],
            ];
        });

        $this->manager->extend('child', function () {
            return [
                'extends' => ['parent1', 'parent2'],
                'prefix' => 'child',
            ];
        });

        $resolved = $this->manager->resolveMount('child');

        $this->assertEquals([
            'key1' => 'from-p1',
            'key2' => 'from-p2',
        ], $resolved['defaults']);
    }
}
