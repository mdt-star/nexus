<?php

namespace MdtStar\Nexus\Services;

use MdtStar\Nexus\Models\Config;
use Illuminate\Support\Facades\Cache;

/**
 * 动态配置管理器
 *
 * 负责动态配置的读写，支持运行时修改。
 * 配置键必须带前缀（如 nexus.upload_max_size）。
 *
 * 持久化合并机制：
 * - boot() 时调用 mergeIntoConfig() 将数据库配置合并到 Laravel Config Repository
 * - set() 写入后自动更新 Config Repository，实现运行时即时生效
 * - delete() 删除后自动从 Config Repository 移除
 */
class DynamicConfigManager
{
    /**
     * 缓存键前缀
     */
    protected string $cachePrefix = 'nexus_config_';

    /**
     * 获取配置值
     *
     * 优先从 Laravel Config Repository 读取（已合并数据库覆盖值），
     * 兜底从数据库直接读取。
     *
     * @param string $key 配置键名（带前缀，如 nexus.upload_max_size）
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 优先从 Config Repository 读取（已合并数据库覆盖值）
        if (config()->has($key)) {
            return config($key, $default);
        }

        // 兜底从数据库读取
        $ttl = config('nexus.dynamic_config.cache_ttl', 3600);

        $config = Cache::remember(
            $this->cachePrefix . $key,
            $ttl,
            fn () => Config::where('key', $key)->first()
        );

        if (! $config) {
            return $default;
        }

        return $config->value;
    }

    /**
     * 设置配置值
     *
     * 写入数据库后，同步更新 Laravel Config Repository，
     * 使 config('nexus.*') 即时生效，无需重启或重新合并。
     *
     * @param string $key 配置键名（带前缀）
     * @param mixed $value 配置值
     * @param string|null $description 配置说明
     * @return Config
     */
    public function set(string $key, mixed $value, ?string $description = null): Config
    {
        $config = Config::updateOrCreate(
            ['key' => $key],
            ['description' => $description]
        );

        $config->value = $value;
        $config->save();

        // 清除缓存
        Cache::forget($this->cachePrefix . $key);

        // 同步更新 Laravel Config Repository，实现运行时即时生效
        $this->syncToConfigRepository($key, $config->value);

        return $config->fresh();
    }

    /**
     * 删除配置
     *
     * 删除后同步从 Laravel Config Repository 移除。
     *
     * @param string $key 配置键名（带前缀）
     * @return bool
     */
    public function delete(string $key): bool
    {
        $deleted = Config::where('key', $key)->delete();

        if ($deleted) {
            Cache::forget($this->cachePrefix . $key);

            // 从 Config Repository 移除，恢复为静态配置值
            $this->removeFromConfigRepository($key);
        }

        return $deleted > 0;
    }

    /**
     * 获取所有配置
     *
     * @return array
     */
    public function all(): array
    {
        return Config::all()->map(function (Config $config) {
            return [
                'key' => $config->key,
                'value' => $config->value,
                'type' => $config->type,
                'description' => $config->description,
            ];
        })->toArray();
    }

    /**
     * 将数据库配置合并到 Laravel Config Repository
     *
     * 在 ServiceProvider::boot() 中调用，使 config('nexus.*') 能读到数据库覆盖值。
     * 数据库配置覆盖静态配置（同名 key 以数据库为准）。
     *
     * 支持点号分隔的嵌套 key（如 nexus.desktop.max_desktops），
     * 自动按层级合并到 Config Repository 中。
     */
    public function mergeIntoConfig(): void
    {
        $configs = Config::all();

        foreach ($configs as $config) {
            $this->syncToConfigRepository($config->key, $config->value);
        }
    }

    /**
     * 将单个配置同步到 Laravel Config Repository
     *
     * 支持点号分隔的嵌套 key（如 nexus.desktop.max_desktops），
     * 使用 config()->set() 按层级设置。
     *
     * @param string $key 配置键名（如 nexus.desktop.max_desktops）
     * @param mixed $value 配置值
     */
    protected function syncToConfigRepository(string $key, mixed $value): void
    {
        config()->set($key, $value);
    }

    /**
     * 从 Laravel Config Repository 移除单个配置
     *
     * 删除数据库配置后调用，使该 key 恢复为静态配置值。
     * 由于 Laravel Config Repository 不支持直接 unset，
     * 通过重新读取静态配置文件来恢复。
     *
     * @param string $key 配置键名
     */
    protected function removeFromConfigRepository(string $key): void
    {
        // 解析顶级配置段（如 nexus.desktop.max_desktops → nexus）
        $segments = explode('.', $key);
        $topKey = $segments[0];

        // 重新加载静态配置文件，覆盖 Config Repository 中的值
        // 这样数据库删除后，该 key 恢复为静态配置值
        $path = config_path($topKey . '.php');
        if (file_exists($path)) {
            $staticConfig = require $path;
            config()->set($topKey, $staticConfig);
        }

        // 重新应用所有数据库配置（确保其他 key 的数据库覆盖不被冲掉）
        $this->mergeIntoConfig();
    }
}
