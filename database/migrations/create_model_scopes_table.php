<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 数据范围策略字典表
     */
    public function up(): void
    {
        Schema::create('model_scopes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key')->unique()->comment('隔离策略唯一 Key（如 article_editor_group）');
            $table->string('class')->unique()->comment('对应后端策略类的全限定命名空间（FQCN）');
            $table->json('model_whitelist')->nullable()->comment('该策略原生声明的模型白名单数组，null 表示不限制模型');
            $table->json('fields_whitelist')->default('["*"]')->comment('该策略原生声明的物理字段白名单数组，默认 ["*"] 表示允许所有字段');
            $table->timestamps();

            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_scopes');
    }
};
