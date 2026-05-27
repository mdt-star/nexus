<?php

namespace MdtStar\Nexus\Http\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 请求/响应参数字段风格转换中间件
 *
 * 支持两种风格：
 * - snake_case（后端内部标准）
 * - camelCase（前端习惯）
 *
 * 前端通过 Header 或参数声明风格，中间件自动完成双向转换：
 * - 请求到达时：camelCase → snake_case（转换 key）
 * - 响应返回时：snake_case → camelCase（转换 key）
 *
 * 检测优先级：
 * 1. Header X-Case: camel / snake
 * 2. Query 参数 _case=camel / snake
 * 3. config('nexus.case.default')，默认 snake
 *
 * 用法（配置在 api mount 中自动生效）：
 * ```php
 * // 前端声明 camelCase
 * GET /api/v1/users?_case=camel
 * // 或
 * Header X-Case: camel
 *
 * // 参数全用驼峰
 * GET /api/v1/users?userId=1&userName=test
 *
 * // 响应自动转驼峰
 * { "userId": 1, "userName": "test" }
 * ```
 */
class CaseMiddleware
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $style = $this->detectStyle($request);

        // 注入到 request attributes，后续环节可读
        $request->attributes->set('_case', $style);

        if ($style === 'camel') {
            // 请求参数转换：camelCase → snake_case
            $this->convertInputKeys($request, [Str::class, 'snake']);
        }

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        if ($style === 'camel' && $response instanceof JsonResponse) {
            $data = json_decode($response->getContent(), true);
            if (is_array($data)) {
                $converted = $this->convertArrayKeys($data, [Str::class, 'camel']);
                $response->setContent(json_encode(
                    $converted,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
            }
        }

        return $response;
    }

    /**
     * 检测前端声明的风格
     *
     * @param Request $request
     * @return string 'camel' | 'snake'
     */
    protected function detectStyle(Request $request): string
    {
        $headerName = config('nexus.case.header_name', 'X-Case');
        $paramName = config('nexus.case.parameter_name', '_case');
        $default = config('nexus.case.default', 'snake');

        // 1. Header 检测
        $header = $request->header($headerName);
        if ($header !== null && in_array($header, ['camel', 'snake'], true)) {
            return $header;
        }

        // 2. 参数检测
        $param = $request->input($paramName);
        if ($param !== null && in_array($param, ['camel', 'snake'], true)) {
            return $param;
        }

        // 3. 配置默认
        return $default;
    }

    /**
     * 转换请求输入参数的 key
     *
     * 将 $request->request、$request->query 和 $request->json 中所有 key 递归转换。
     *
     * @param Request $request
     * @param callable $convert Str::snake | Str::camel
     */
    protected function convertInputKeys(Request $request, callable $convert): void
    {
        // 替换 request 参数（表单/POST 参数）
        $request->request->replace(
            $this->convertArrayKeys($request->request->all(), $convert)
        );

        // 替换 query 参数（GET 参数）
        $request->query->replace(
            $this->convertArrayKeys($request->query->all(), $convert)
        );

        // 替换 json 参数（JSON body 请求）
        if ($request->isJson() && $request->json() !== null) {
            $request->json()->replace(
                $this->convertArrayKeys($request->json()->all(), $convert)
            );
        }
    }

    /**
     * 递归转换数组的所有 key
     *
     * @param array $input
     * @param callable $convert Str::snake | Str::camel
     * @return array
     */
    protected function convertArrayKeys(array $input, callable $convert): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            $newKey = $convert($key);

            if (is_array($value)) {
                $result[$newKey] = $this->convertArrayKeys($value, $convert);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
