<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class OptimizeV2SettingsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('v2_settings', function (Blueprint $table) {
      // 将 value 字段改为 MEDIUMTEXT，支持最大16MB内容
      $table->mediumText('value')->nullable()->change();
      // 添加优化索引
      $table->index('name', 'idx_setting_name');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('v2_settings', function (Blueprint $table) {
      $table->string('value')->nullable()->change();
      $table->dropIndex('idx_setting_name');
    });
  }
}