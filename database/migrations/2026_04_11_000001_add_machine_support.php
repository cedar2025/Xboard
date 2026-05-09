<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_server_machine', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('last_seen_at')->nullable();
            $table->json('load_status')->nullable();
            $table->timestamps();
        });

        Schema::create('v2_server_machine_load_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('machine_id');
            $table->float('cpu')->default(0);
            $table->unsignedBigInteger('mem_total')->default(0);
            $table->unsignedBigInteger('mem_used')->default(0);
            $table->unsignedBigInteger('disk_total')->default(0);
            $table->unsignedBigInteger('disk_used')->default(0);
            $table->unsignedInteger('recorded_at');
            $table->timestamps();

            $table->foreign('machine_id')->references('id')->on('v2_server_machine')->cascadeOnDelete();
            $table->index(['machine_id', 'recorded_at']);
        });

        Schema::table('v2_server', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable()->after('parent_id');
            $table->boolean('enabled')->default(true)->after('show');

            $table->foreign('machine_id')->references('id')->on('v2_server_machine')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('v2_server', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn(['machine_id', 'enabled']);
        });
        Schema::dropIfExists('v2_server_machine_load_history');
        Schema::dropIfExists('v2_server_machine');
    }
};
