<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficResetLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('v2_traffic_reset_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->comment('用户ID');
            $table->string('reset_type', 50)->comment('重置类型');
            $table->timestamp('reset_time')->comment('重置时间');
            $table->bigInteger('old_upload')->default(0)->comment('重置前上传流量');
            $table->bigInteger('old_download')->default(0)->comment('重置前下载流量');
            $table->bigInteger('old_total')->default(0)->comment('重置前总流量');
            $table->bigInteger('new_upload')->default(0)->comment('重置后上传流量');
            $table->bigInteger('new_download')->default(0)->comment('重置后下载流量');
            $table->bigInteger('new_total')->default(0)->comment('重置后总流量');
            $table->string('trigger_source', 50)->comment('触发来源');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id', 'idx_user_id');
            $table->index('reset_time', 'idx_reset_time');
            $table->index(['user_id', 'reset_time'], 'idx_user_reset_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_traffic_reset_logs');
    }
} 