<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

/**
 * Package 模型单元测试
 *
 * 覆盖：
 * - allCached() 全表缓存
 * - idByName() 通过包名获取 ID
 * - flushCache() 清理缓存
 */
class PackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /** @test */
    public function allCached返回按name索引的集合()
    {
        Package::create(['name' => 'test/package-a']);
        Package::create(['name' => 'test/package-b']);

        $cached = Package::allCached();

        $this->assertCount(2, $cached);
        $this->assertTrue($cached->has('test/package-a'));
        $this->assertTrue($cached->has('test/package-b'));
        $this->assertEquals('test/package-a', $cached['test/package-a']->name);
    }

    /** @test */
    public function allCached走缓存()
    {
        Package::create(['name' => 'test/package']);

        // 首次调用缓存
        $cached = Package::allCached();
        $this->assertCount(1, $cached);

        // 新增包（绕过缓存）
        Package::create(['name' => 'test/package-new']);

        // 缓存未清理，应返回旧数据
        $cached = Package::allCached();
        $this->assertCount(1, $cached);

        // 清理缓存后返回新数据
        Package::flushCache();
        $cached = Package::allCached();
        $this->assertCount(2, $cached);
    }

    /** @test */
    public function idByName返回正确的id()
    {
        $package = Package::create(['name' => 'test/package']);

        $id = Package::idByName('test/package');

        $this->assertEquals($package->id, $id);
    }

    /** @test */
    public function idByName不存在的包返回null()
    {
        $this->assertNull(Package::idByName('test/nonexistent'));
    }

    /** @test */
    public function flushCache清理全表缓存()
    {
        Package::create(['name' => 'test/package']);

        // 触发缓存
        Package::allCached();
        $this->assertTrue(Cache::has('nexus_packages_all'));

        Package::flushCache();
        $this->assertFalse(Cache::has('nexus_packages_all'));
    }
}
