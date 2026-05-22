<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 功能权限节点字典表
     *
     * name 通过多国语言包匹配 tag 获取，不存储在数据库。
     * (package_id, tag, parent_id) 联合唯一，不同包可以有同名 tag。
     * tag 原样保存，不拼接父级前缀，树形结构靠 parent_id 维护。
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级 id，null 为顶级');
            $table->unsignedBigInteger('package_id')->nullable()->comment('关联 packages.id，null 为全局权限');
            $table->string('tag')->comment('权限标识（如 article:list, article:add）');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('package_id');
            $table->unique(['package_id', 'tag', 'parent_id'], 'uk_package_tag_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
