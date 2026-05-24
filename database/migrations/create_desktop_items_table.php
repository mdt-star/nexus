<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 桌面项表
     *
     * 用户桌面上的菜单项，由前端直接创建/更新。
     * 不再关联 menus 表，所有字段直接存储。
     */
    public function up(): void
    {
        Schema::create('desktop_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('desktop_id')->comment('关联 desktops.id');
            $table->string('label')->comment('菜单标题');
            $table->string('path')->comment('路由路径');
            $table->string('component')->nullable()->comment('前端组件路径');
            $table->string('icon')->nullable()->comment('图标类名');
            $table->json('custom')->nullable()->comment('自定义扩展数据');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('desktop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_items');
    }
};
