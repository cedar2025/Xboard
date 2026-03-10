<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_admin_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('action', 64)->index()->comment('Action identifier e.g. user.update');
            $table->string('method', 10);
            $table->string('uri', 512);
            $table->text('request_data')->nullable();
            $table->string('ip', 128)->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_admin_audit_log');
    }
};
