<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Exceptions\PermissionDeniedException;
use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permissionable;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * VerifyAuthTagMiddleware 权限标签检查集成测试
 *
 * 覆盖：
 * - 未登录访问受保护路由 → 401
 * - 超级管理员跳过权限检查
 * - 有权限的用户可以访问
 * - 无权限的用户被拒绝
 * - 中间件参数指定 tag
 * - Route::tag() 自定义 tag
 * - 无 tag 可推断时抛 tag_not_found
 * - 用户未实现 HasPermission 接口
 * - package_id 精确匹配
 * - 全局 tag（package_id IS NULL）匹配
 */
class AuthTagMiddlewareTest extends TestCase
{
    protected User $user;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->package = Package::create(['name' => 'test/article']);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function 未登录访问受保护路由返回401()
    {
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(401);
    }

    /** @test */
    public function 超级管理员跳过权限检查()
    {
        Config::set('nexus.super_admin_id', $this->user->id);
        $this->actingAs($this->user);

        // 不授予任何 tag，超级管理员应能通过
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 有权限的用户可以访问()
    {
        // 授予全局 desktop:list tag（package_id = null）
        // Route::admin() 注册的路由没有 package_id defaults
        // 所以 hasTag 会传 package_id=null，需要匹配 package_id IS NULL 的记录
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        // GET /api/v1/admin/desktops → DesktopController@index → 自动推断 tag=desktop:list
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 无权限的用户被拒绝()
    {
        // 不授予任何 tag
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing desktop:list permission']);
    }

    /** @test */
    public function 中间件参数指定tag有权限可通过()
    {
        // 注册一条测试路由，显式指定 tag
        Route::get('/api/test-tag-param', function () {
            return 'ok';
        })->middleware('auth.tag:custom:tag');

        // 授予 custom:tag
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-param');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function 中间件参数指定tag无权限被拒绝()
    {
        Route::get('/api/test-tag-param-deny', function () {
            return 'ok';
        })->middleware('auth.tag:custom:tag');

        // 不授予 custom:tag
        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-param-deny');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing custom:tag permission']);
    }

    /** @test */
    public function route_tag自定义tag有权限可通过()
    {
        // Route::tag() 是 Route facade 的宏，不能在 Route 实例上调用
        // 改用 defaults() 设置 auth_tag
        Route::get('/api/test-tag-method', function () {
            return 'ok';
        })->middleware('auth.tag')->setDefaults(['auth_tag' => 'article:list']);

        // 授予 article:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-method');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function route_tag自定义tag无权限被拒绝()
    {
        Route::get('/api/test-tag-method-deny', function () {
            return 'ok';
        })->middleware('auth.tag')->setDefaults(['auth_tag' => 'article:list']);

        // 不授予 article:list
        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-method-deny');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing article:list permission']);
    }

    /** @test */
    public function 无tag可推断时抛tag_not_found()
    {
        // 闭包路由，无控制器可推断，无中间件参数，无 defaults
        Route::get('/api/test-no-tag', function () {
            return 'ok';
        })->middleware('auth.tag');

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-no-tag');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Unable to determine permission tag']);
    }

    /** @test */
    public function 用户未实现HasPermission接口被拒绝()
    {
        // 用普通用户（非 Nexus User）登录
        $plainUser = new \Illuminate\Foundation\Auth\User();
        $plainUser->id = 999;

        $this->actingAs($plainUser);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Subject does not implement permission interface']);
    }

    /** @test */
    public function package_id精确匹配有权限可通过()
    {
        // 授予 package1 的 desktop:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        // 注册一条带 package_id defaults 的路由
        Route::get('/api/test-package-match', function () {
            return 'ok';
        })->middleware('auth.tag:desktop:list')->setDefaults([
            'package_id' => $this->package->id,
        ]);

        $response = $this->getJson('/api/test-package-match');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function package_id精确匹配无权限被拒绝()
    {
        $package2 = Package::create(['name' => 'test/other']);

        // 只授予 package1 的 desktop:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        // 请求 package2 的 desktop:list → 不匹配
        Route::get('/api/test-package-mismatch', function () {
            return 'ok';
        })->middleware('auth.tag:desktop:list')->setDefaults([
            'package_id' => $package2->id,
        ]);

        $response = $this->getJson('/api/test-package-mismatch');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing desktop:list permission']);
    }

    /** @test */
    public function 全局tag无package_id可匹配()
    {
        // 授予全局 tag（package_id = null）
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        // 请求路由，路由无 package_id defaults
        // hasTag 应匹配 package_id IS NULL 的全局 tag
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 角色tag穿透到用户可通过权限检查()
    {
        $role = \MdtStar\Nexus\Models\Role::create(['name' => 'editor', 'slug' => 'editor']);
        $this->user->roles()->attach($role);

        // 给角色授予全局 tag（package_id = null）
        Permissionable::create([
            'model_type' => \MdtStar\Nexus\Models\Role::class,
            'model_id' => $role->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }
}
