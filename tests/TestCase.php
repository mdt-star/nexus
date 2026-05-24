<?php

namespace MdtStar\Nexus\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * 测试基类
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * 加载包服务提供者
     */
    protected function getPackageProviders($app): array
    {
        return [
            \MdtStar\Nexus\Providers\NexusServiceProvider::class,
        ];
    }

    /**
     * 设置环境
     */
    protected function defineEnvironment($app): void
    {
        // 使用 SQLite 内存数据库进行测试
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // 测试环境中不启用超级管理员（避免 id=1 的用户被跳过权限检查）
        $app['config']->set('nexus.super_admin_id', 0);
    }

    /**
     * 运行迁移
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
