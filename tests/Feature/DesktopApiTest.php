<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Models\Desktop;
use MdtStar\Nexus\Models\DesktopItem;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Desktop + DesktopItem API 集成测试
 *
 * 覆盖：
 * - Desktop CRUD
 * - DesktopItem CRUD（嵌套路由）
 * - DesktopItem reorder 批量排序
 * - DesktopItem 树状结构（parent_id）
 * - 过滤（user_id, region, is_default）
 */
class DesktopApiTest extends TestCase
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

    /** @test */
    public function 创建桌面()
    {
        $response = $this->postJson('/api/v1/admin/desktops', [
            'user_id' => $this->user->id,
            'name' => '主桌面',
            'region' => 'sidebar_left',
            'is_default' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('desktops', [
            'user_id' => $this->user->id,
            'name' => '主桌面',
            'region' => 'sidebar_left',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function 获取桌面列表()
    {
        Desktop::create(['user_id' => $this->user->id, 'name' => '桌面A', 'region' => 'main']);
        Desktop::create(['user_id' => $this->user->id, 'name' => '桌面B', 'region' => 'sidebar_left']);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 按user_id过滤桌面()
    {
        $user2 = User::create(['name' => 'User2', 'email' => 'u2@test.com', 'password' => bcrypt('p')]);
        Desktop::create(['user_id' => $this->user->id, 'name' => '我的桌面', 'region' => 'main']);
        Desktop::create(['user_id' => $user2->id, 'name' => 'User2桌面', 'region' => 'main']);

        $response = $this->getJson('/api/v1/admin/desktops?user_id=' . $this->user->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => '我的桌面']);
    }

    /** @test */
    public function 按region过滤桌面()
    {
        Desktop::create(['user_id' => $this->user->id, 'name' => '桌面A', 'region' => 'main']);
        Desktop::create(['user_id' => $this->user->id, 'name' => '桌面B', 'region' => 'sidebar_left']);

        $response = $this->getJson('/api/v1/admin/desktops?region=main');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => '桌面A']);
    }

    /** @test */
    public function 按is_default过滤桌面()
    {
        Desktop::create(['user_id' => $this->user->id, 'name' => '默认桌面', 'region' => 'main', 'is_default' => true]);
        Desktop::create(['user_id' => $this->user->id, 'name' => '备用桌面', 'region' => 'sidebar_left', 'is_default' => false]);

        $response = $this->getJson('/api/v1/admin/desktops?is_default=1');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => '默认桌面']);
    }

    /** @test */
    public function 更新桌面()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '旧名称', 'region' => 'main']);

        $response = $this->putJson('/api/v1/admin/desktops/' . $desktop->id, [
            'name' => '新名称',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('desktops', ['id' => $desktop->id, 'name' => '新名称']);
    }

    /** @test */
    public function 删除桌面同时删除其桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '待删除', 'region' => 'main']);
        $item = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '项', 'path' => '/test', 'sort' => 0]);

        $response = $this->deleteJson('/api/v1/admin/desktops/' . $desktop->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('desktops', ['id' => $desktop->id]);
        $this->assertDatabaseMissing('desktop_items', ['id' => $item->id]);
    }

    /** @test */
    public function 创建桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);

        $response = $this->postJson('/api/v1/admin/desktops/' . $desktop->id . '/items', [
            'label' => '文章管理',
            'path' => '/articles',
            'icon' => 'article-icon',
            'component' => 'Article/List',
            'sort' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('desktop_items', [
            'desktop_id' => $desktop->id,
            'label' => '文章管理',
            'path' => '/articles',
        ]);
    }

    /** @test */
    public function 获取桌面项列表()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '项A', 'path' => '/a', 'sort' => 0]);
        DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '项B', 'path' => '/b', 'sort' => 1]);

        $response = $this->getJson('/api/v1/admin/desktops/' . $desktop->id . '/items');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    /** @test */
    public function 更新桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $item = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '旧标签', 'path' => '/old', 'sort' => 0]);

        $response = $this->putJson('/api/v1/admin/desktops/' . $desktop->id . '/items/' . $item->id, [
            'label' => '新标签',
            'path' => '/new',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('desktop_items', ['id' => $item->id, 'label' => '新标签', 'path' => '/new']);
    }

    /** @test */
    public function 删除桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $item = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '待删除', 'path' => '/del', 'sort' => 0]);

        $response = $this->deleteJson('/api/v1/admin/desktops/' . $desktop->id . '/items/' . $item->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('desktop_items', ['id' => $item->id]);
    }

    /** @test */
    public function 批量排序桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $item1 = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '项A', 'path' => '/a', 'sort' => 0]);
        $item2 = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '项B', 'path' => '/b', 'sort' => 1]);

        $response = $this->putJson('/api/v1/admin/desktops/' . $desktop->id . '/items/reorder', [
            'items' => [
                ['id' => $item1->id, 'sort' => 1],
                ['id' => $item2->id, 'sort' => 0],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('desktop_items', ['id' => $item1->id, 'sort' => 1]);
        $this->assertDatabaseHas('desktop_items', ['id' => $item2->id, 'sort' => 0]);
    }

    /** @test */
    public function 创建子级桌面项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $parent = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '父项', 'path' => '/parent', 'sort' => 0]);

        $response = $this->postJson('/api/v1/admin/desktops/' . $desktop->id . '/items', [
            'label' => '子项',
            'path' => '/parent/child',
            'parent_id' => $parent->id,
            'sort' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('desktop_items', [
            'desktop_id' => $desktop->id,
            'label' => '子项',
            'parent_id' => $parent->id,
        ]);
    }

    /** @test */
    public function 获取桌面项列表返回树状结构()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $parent = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '父项', 'path' => '/parent', 'sort' => 0]);
        DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '子项', 'path' => '/parent/child', 'parent_id' => $parent->id, 'sort' => 0]);
        DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '独立项', 'path' => '/alone', 'sort' => 1]);

        $response = $this->getJson('/api/v1/admin/desktops/' . $desktop->id . '/items');

        $response->assertStatus(200);
        // 应返回 2 个根节点（父项 + 独立项），子项嵌套在父项的 children 中
        $response->assertJsonCount(2);

        // 验证父项包含子项
        $response->assertJsonFragment(['label' => '父项']);
        $response->assertJsonFragment(['label' => '独立项']);

        // 验证子项在 children 中（不在根层级）
        $json = $response->json();
        $parentNode = collect($json)->firstWhere('label', '父项');
        $this->assertNotNull($parentNode);
        $this->assertArrayHasKey('children', $parentNode);
        $this->assertCount(1, $parentNode['children']);
        $this->assertEquals('子项', $parentNode['children'][0]['label']);
    }

    /** @test */
    public function 更新桌面项parent_id()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $parent = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '父项', 'path' => '/parent', 'sort' => 0]);
        $child = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '子项', 'path' => '/child', 'sort' => 1]);

        // 将子项挂到父项下
        $response = $this->putJson('/api/v1/admin/desktops/' . $desktop->id . '/items/' . $child->id, [
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('desktop_items', ['id' => $child->id, 'parent_id' => $parent->id]);
    }

    /** @test */
    public function 删除父级桌面项级联删除子项()
    {
        $desktop = Desktop::create(['user_id' => $this->user->id, 'name' => '桌面', 'region' => 'main']);
        $parent = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '父项', 'path' => '/parent', 'sort' => 0]);
        $child = DesktopItem::create(['desktop_id' => $desktop->id, 'label' => '子项', 'path' => '/child', 'parent_id' => $parent->id, 'sort' => 0]);

        $response = $this->deleteJson('/api/v1/admin/desktops/' . $desktop->id . '/items/' . $parent->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('desktop_items', ['id' => $parent->id]);
        $this->assertDatabaseMissing('desktop_items', ['id' => $child->id]);
    }
}
