<?php

namespace MdtStar\Nexus\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 权限拒绝异常
 *
 * 当作用主体对模型无读取/写入/删除权限时抛出，
 * 继承 Symfony HttpException，Laravel 自动转为 403 HTTP 响应。
 *
 * 消息通过语言包 key 加载，支持国际化。
 *
 * 使用示例：
 * ```php
 * throw new PermissionDeniedException('no_read_permission');
 * throw new PermissionDeniedException('scope_not_found');
 * ```
 */
class PermissionDeniedException extends HttpException
{
    /**
     * @param string $key exceptions 语言包中的键名
     * @param array $replace 语言包替换参数
     * @param string|null $locale 指定语言，null 使用当前 app locale
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $key = 'permission_denied',
        array $replace = [],
        ?string $locale = null,
        ?\Throwable $previous = null
    ) {
        $message = __("nexus::exceptions.{$key}", $replace, $locale);
        parent::__construct(403, $message, $previous);
    }
}
