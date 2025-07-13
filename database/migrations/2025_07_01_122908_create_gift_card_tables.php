<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 礼品卡模板表
        Schema::create('v2_gift_card_template', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('礼品卡名称');
            $table->text('description')->nullable()->comment('礼品卡描述');
            $table->tinyInteger('type')->comment('卡片类型：1余额 2有效期 3流量 4重置包 5套餐 6组合 7盲盒 8任务 9等级 10节日');
            $table->tinyInteger('status')->default(1)->comment('状态：0禁用 1启用');
            $table->json('conditions')->nullable()->comment('使用条件配置');
            $table->json('rewards')->comment('奖励配置');
            $table->json('limits')->nullable()->comment('限制条件');
            $table->json('special_config')->nullable()->comment('特殊配置(节日时间、等级倍率等)');
            $table->string('icon')->nullable()->comment('卡片图标');
            $table->string('background_image')->nullable()->comment('背景图片URL');
            $table->string('theme_color', 7)->default('#1890ff')->comment('主题色');
            $table->integer('sort')->default(0)->comment('排序');
            $table->integer('admin_id')->comment('创建管理员ID');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->index(['type', 'status'], 'idx_gift_template_type_status');
            $table->index('created_at', 'idx_gift_template_created_at');
        });

        // 礼品卡兑换码表
        Schema::create('v2_gift_card_code', function (Blueprint $table) {
            $table->id();
            $table->integer('template_id')->comment('模板ID');
            $table->string('code', 32)->unique()->comment('兑换码');
            $table->string('batch_id', 32)->nullable()->comment('批次ID');
            $table->tinyInteger('status')->default(0)->comment('状态：0未使用 1已使用 2已过期 3已禁用');
            $table->integer('user_id')->nullable()->comment('使用用户ID');
            $table->integer('used_at')->nullable()->comment('使用时间');
            $table->integer('expires_at')->nullable()->comment('过期时间');
            $table->json('actual_rewards')->nullable()->comment('实际获得的奖励(用于盲盒等)');
            $table->integer('usage_count')->default(0)->comment('使用次数(分享卡)');
            $table->integer('max_usage')->default(1)->comment('最大使用次数');
            $table->json('metadata')->nullable()->comment('额外数据');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->index('template_id', 'idx_gift_code_template_id');
            $table->index('status', 'idx_gift_code_status');
            $table->index('user_id', 'idx_gift_code_user_id');
            $table->index('batch_id', 'idx_gift_code_batch_id');
            $table->index('expires_at', 'idx_gift_code_expires_at');
            $table->index(['code', 'status', 'expires_at'], 'idx_gift_code_lookup');
        });

        // 礼品卡使用记录表
        Schema::create('v2_gift_card_usage', function (Blueprint $table) {
            $table->id();
            $table->integer('code_id')->comment('兑换码ID');
            $table->integer('template_id')->comment('模板ID');
            $table->integer('user_id')->comment('使用用户ID');
            $table->integer('invite_user_id')->nullable()->comment('邀请人ID');
            $table->json('rewards_given')->comment('实际发放的奖励');
            $table->json('invite_rewards')->nullable()->comment('邀请人获得的奖励');
            $table->integer('user_level_at_use')->nullable()->comment('使用时用户等级');
            $table->integer('plan_id_at_use')->nullable()->comment('使用时用户套餐ID');
            $table->decimal('multiplier_applied', 3, 2)->default(1.00)->comment('应用的倍率');
            $table->string('ip_address', 45)->nullable()->comment('使用IP地址');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->text('notes')->nullable()->comment('备注');
            $table->integer('created_at');

            $table->index('code_id', 'idx_gift_usage_code_id');
            $table->index('template_id', 'idx_gift_usage_template_id');
            $table->index('user_id', 'idx_gift_usage_user_id');
            $table->index('invite_user_id', 'idx_gift_usage_invite_user_id');
            $table->index('created_at', 'idx_gift_usage_created_at');
            $table->index(['user_id', 'created_at'], 'idx_gift_usage_user_usage');
            $table->index(['template_id', 'created_at'], 'idx_gift_usage_template_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_gift_card_usage');
        Schema::dropIfExists('v2_gift_card_code');
        Schema::dropIfExists('v2_gift_card_template');
    }
};
