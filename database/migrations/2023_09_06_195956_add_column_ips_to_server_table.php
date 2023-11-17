<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIpsToServerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_server_hysteria', function (Blueprint $table) {
            $table->string("ips")->nullable()->after('excludes');
        });
        Schema::table('v2_server_shadowsocks', function (Blueprint $table) {
            $table->string("ips")->nullable()->after('excludes');
        });
        Schema::table('v2_server_trojan', function (Blueprint $table) {
            $table->string("ips")->nullable()->after('excludes');
        });
        Schema::table('v2_server_vless', function (Blueprint $table) {
            $table->string("ips")->nullable()->after('excludes');
        });
        Schema::table('v2_server_vmess', function (Blueprint $table) {
            $table->string("ips")->nullable()->after('excludes');
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
            $table->dropColumn('ips');
        });
        Schema::table('v2_server_shadowsocks', function (Blueprint $table) {
            $table->dropColumn('ips');
        });
        Schema::table('v2_server_trojan', function (Blueprint $table) {
            $table->dropColumn('ips');
        });
        Schema::table('v2_server_vless', function (Blueprint $table) {
            $table->dropColumn('ips');
        });
        Schema::table('v2_server_vmess', function (Blueprint $table) {
            $table->dropColumn('ips');
        });
    }
}
