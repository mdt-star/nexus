<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 分布式动态配置持久化表
     *
     * 数组存储为 JSON 字符串，通过 value_type 自省标签反序列化。
     */
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key')->unique()->comment('完整配置键名（必须带前缀，如 core.upload_max_size）');
            $table->text('value')->comment('持久化配置值（数组存储为 JSON 字符串）');
            $table->string('type')->default('string')->comment('类型自省标签（string, boolean, json, number）');
            $table->string('description')->nullable()->comment('配置说明');
            $table->timestamps();

            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
