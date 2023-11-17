<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAlpnToServerHysteriaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_server_hysteria', function (Blueprint $table) {
            $table->tinyInteger('alpn',false,true)->default(0)->comment('ALPN,0:hysteria、1:http/1.1、2:h2、3:h3');
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
            $table->dropColumn('alpn');
        });
    }
}
