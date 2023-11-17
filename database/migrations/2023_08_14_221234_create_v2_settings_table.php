<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateV2SettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('v2_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->comment('设置分组')->nullable();
            $table->string('type')->comment('设置类型')->nullable();
            $table->string('name')->comment('设置名称')->uniqid();
            $table->string('value')->comment('设置值')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('v2_settings');
    }
}
