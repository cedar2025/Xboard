<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnVersionAndIsObfsToServerHysteriaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_server_hysteria', function (Blueprint $table) {
            $table->tinyInteger('version',false,true)->default(1)->comment('hysteria版本,Version:1\2');
            $table->boolean('is_obfs')->default(true)->comment('是否开启obfs');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('v2_server_hysteria', function (Blueprint $table) {
            $table->dropColumn('version','is_obfs');
        });
    }
}
