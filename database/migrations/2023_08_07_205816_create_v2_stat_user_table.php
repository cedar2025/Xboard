<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('v2_stat_user', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id');
            $table->decimal('server_rate', 10);
            $table->bigInteger('u');
            $table->bigInteger('d');
            $table->char('record_type', 2);
            $table->integer('record_at');
            $table->integer('created_at');
            $table->integer('updated_at');

            // 如果是不是sqlite才添加多个索引
            if(config('database.default') !== 'sqlite'){
                $table->index(['user_id','server_rate','record_at']);
                $table->unique(['server_rate', 'user_id', 'record_at'], 'server_rate_user_id_record_at');
            }

            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('v2_stat_user');
    }
};
