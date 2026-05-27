<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 模型访问权限表
     *
     * 控制主体（用户/角色/团队等）对特定模型的读写删权限及数据范围。
     * 与 permissions（功能标记表）不同，本表控制的是数据层面的访问权限。
     */
    public function up(): void
    {
        Schema::create('model_accesses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subject');
            $table->string('class')->comment('强控的 Eloquent Model 全限定名（FQCN）');
            $table->boolean('can_read')->default(true)->comment('是否允许读取');
            $table->boolean('can_write')->default(true)->comment('是否允许写入/更新');
            $table->boolean('can_delete')->default(true)->comment('是否允许删除');
            $table->string('scope_key')->nullable()->comment('绑定的 Scope Key');
            $table->timestamps();

            $table->index('class');
            $table->index('scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_accesses');
    }
};
