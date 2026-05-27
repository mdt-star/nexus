<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户桌面配置表
     */
    public function up(): void
    {
        Schema::create('desktops', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户 id');
            $table->string('name')->comment('桌面名称');
            $table->string('region')->comment('布局区域（如 sidebar_left, header, main）');
            $table->boolean('is_default')->default(false)->comment('是否为默认桌面（同 region 下默认桌面优先返回）');
            $table->timestamps();

            $table->index('user_id');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktops');
    }
};
