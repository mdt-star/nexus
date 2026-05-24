<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 菜单发布池
     *
     * 通过 parent_id 与 permissions 表保持一致的树形层级关系。
     * 落库时 path 保持原始值，数据返回时动态拼接完整路径。
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级 id，null 为顶级（关联 permissions.id）');
            $table->string('label')->comment('菜单标题');
            $table->string('path')->comment('路由路径（相对路径，动态拼接时使用）');
            $table->string('component')->nullable()->comment('前端组件路径（如 Article/List）');
            $table->string('icon')->nullable()->comment('图标类名');
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
