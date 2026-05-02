<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->integer('deleted_at')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
