<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permission;
use MdtStar\Nexus\Services\PermissionSyncer;
use MdtStar\Nexus\Tests\TestCase;

/**
 * PermissionSyncer 单元测试
 *
 * 覆盖：
 * - sync() 树形同步
 * - uninstall() 清理
 * - tag 原样保存，不拼接父级前缀
 * - 唯一约束 (package_id, tag, parent_id)
 */
class PermissionSyncerTest extends TestCase
{
    protected PermissionSyncer $syncer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->syncer = new PermissionSyncer;
    }

    /** @test */
    public function 同步顶级权限()
    {
        $this->syncer->sync('test/article', [
            'nexus' => [
                'permissions' => [
                    ['tag' => 'article'],
                ],
            ],
        ]);

        $package = Package::where('name', 'test/article')->first();
        $this->assertNotNull($package);

        $permission = Permission::where('package_id', $package->id)->first();
        $this->assertEquals('article', $permission->tag);
        $this->assertNull($permission->parent_id);
    }

    /** @test */
    public function 同步树形权限tag不拼接父级前缀()
    {
        $this->syncer->sync('test/article', [
            'nexus' => [
                'permissions' => [
                    [
                        'tag' => 'article',
                        'children' => [
                            ['tag' => 'list'],
                            ['tag' => 'add'],
                        ],
                    ],
                ],
            ],
        ]);

        $package = Package::where('name', 'test/article')->first();

        $parent = Permission::where('package_id', $package->id)->whereNull('parent_id')->first();
        $this->assertEquals('article', $parent->tag);

        $children = Permission::where('package_id', $package->id)->where('parent_id', $parent->id)->get();
        $this->assertCount(2, $children);
        $this->assertEquals('list', $children[0]->tag);
        $this->assertEquals('add', $children[1]->tag);
    }

    /** @test */
    public function 同步字符串数组简写格式()
    {
        $this->syncer->sync('test/article', [
            'nexus' => [
                'permissions' => [
                    [
                        'tag' => 'article',
                        'children' => ['list', 'add', 'edit'],
                    ],
                ],
            ],
        ]);

        $package = Package::where('name', 'test/article')->first();
        $parent = Permission::where('package_id', $package->id)->whereNull('parent_id')->first();

        $children = Permission::where('package_id', $package->id)->where('parent_id', $parent->id)->get();
        $this->assertCount(3, $children);
        $this->assertEquals('list', $children[0]->tag);
        $this->assertEquals('add', $children[1]->tag);
        $this->assertEquals('edit', $children[2]->tag);
    }

    /** @test */
    public function 重复同步不会产生重复数据()
    {
        $config = [
            'nexus' => [
                'permissions' => [
                    ['tag' => 'article'],
                ],
            ],
        ];

        $this->syncer->sync('test/article', $config);
        $this->syncer->sync('test/article', $config);

        $package = Package::where('name', 'test/article')->first();
        $this->assertNotNull($package);

        $permissions = Permission::where('package_id', $package->id)->get();
        $this->assertCount(1, $permissions);
    }

    /** @test */
    public function 不同包可以有同名tag()
    {
        $this->syncer->sync('test/article', [
            'nexus' => ['permissions' => [['tag' => 'list']]],
        ]);
        $this->syncer->sync('test/comment', [
            'nexus' => ['permissions' => [['tag' => 'list']]],
        ]);

        $package1 = Package::where('name', 'test/article')->first();
        $package2 = Package::where('name', 'test/comment')->first();

        $this->assertCount(1, Permission::where('package_id', $package1->id)->get());
        $this->assertCount(1, Permission::where('package_id', $package2->id)->get());
    }

    /** @test */
    public function uninstall清理包和关联权限()
    {
        $this->syncer->sync('test/article', [
            'nexus' => [
                'permissions' => [
                    [
                        'tag' => 'article',
                        'children' => ['list', 'add'],
                    ],
                ],
            ],
        ]);

        $this->syncer->uninstall('test/article');

        $this->assertNull(Package::where('name', 'test/article')->first());
        $this->assertCount(0, Permission::all());
    }

    /** @test */
    public function uninstall不存在的包不报错()
    {
        $this->syncer->uninstall('test/nonexistent');

        $this->assertTrue(true); // 没有异常即通过
    }
}
