<?php

namespace MdtStar\Nexus\Tests\Unit;

use MdtStar\Nexus\Exceptions\PermissionDeniedException;
use MdtStar\Nexus\Tests\TestCase;

/**
 * PermissionDeniedException 单元测试
 *
 * 覆盖：
 * - 国际化消息加载
 * - 替换参数
 * - 默认 key
 * - HTTP 状态码 403
 */
class PermissionDeniedExceptionTest extends TestCase
{
    /** @test */
    public function 默认key返回permission_denied消息()
    {
        $exception = new PermissionDeniedException;

        $this->assertEquals(403, $exception->getStatusCode());
        $this->assertIsString($exception->getMessage());
    }

    /** @test */
    public function 指定key加载对应语言包消息()
    {
        $exception = new PermissionDeniedException('tag_not_found', [], 'zh_CN');

        $this->assertEquals('无法确定权限标识', $exception->getMessage());
    }

    /** @test */
    public function 替换参数正确渲染()
    {
        $exception = new PermissionDeniedException('no_tag_permission', ['tag' => 'article:list'], 'zh_CN');

        $this->assertEquals('权限不足，缺少 article:list 权限', $exception->getMessage());
    }

    /** @test */
    public function 指定英文locale()
    {
        $exception = new PermissionDeniedException('tag_not_found', [], 'en');

        $this->assertEquals('Unable to determine permission tag', $exception->getMessage());
    }

    /** @test */
    public function 状态码始终为403()
    {
        $exception = new PermissionDeniedException('tag_not_found');

        $this->assertEquals(403, $exception->getStatusCode());
    }
}
