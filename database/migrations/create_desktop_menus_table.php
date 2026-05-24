<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 桌面菜单关联表
     *
     * 用户可将菜单从 menus 拖拽到桌面，并支持自定义覆盖原菜单项的值。
     * 模型层自动合并 custom 与 menus 原值，优先使用 custom。
     */
    public function up(): void
    {
        Schema::create('desktop_menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('desktop_id')->comment('关联 desktops.id');
            $table->unsignedBigInteger('menu_id')->comment('关联 menus.id');
            $table->json('custom')->nullable()->comment('自定义覆盖值（如 {"label": "我的文章", "path": "my-article"}）');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('desktop_id');
            $table->index('menu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_menus');
    }
};
