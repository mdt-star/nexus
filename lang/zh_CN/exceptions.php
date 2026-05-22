<?php

/**
 * 异常消息中文映射
 *
 * 通过 __('nexus::exceptions.xxx') 加载
 */
return [
    'invalid_subject' => '无效的作用主体，无法获取权限信息',
    'subject_not_has_permissions' => '作用主体异常，无法获取权限信息',
    'no_read_permission' => '权限不足，无法查看此内容',
    'no_write_permission' => '权限不足，无法执行写入操作',
    'no_delete_permission' => '权限不足，无法执行删除操作',
    'scope_not_found' => '数据范围策略配置异常，无法执行查询',
    'scope_model_not_in_whitelist' => '当前模型不在数据范围策略白名单中',
    'scope_class_not_found' => '数据范围策略类不存在，请联系管理员',
    'scope_execution_failed' => '数据范围策略执行异常，无法完成查询',
    'tag_not_found' => '无法确定权限标识',
    'subject_not_has_permission_interface' => '主体未实现权限接口',
    'no_tag_permission' => '权限不足，缺少 :tag 权限',
];
