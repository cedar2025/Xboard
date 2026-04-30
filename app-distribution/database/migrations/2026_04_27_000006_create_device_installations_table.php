<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('installation_id', 128);
            $table->string('platform', 32)->index();
            $table->string('channel', 32)->default('stable')->index();
            $table->string('arch', 32)->nullable();
            $table->string('version', 32)->nullable();
            $table->unsignedInteger('build_number')->default(0);
            $table->string('os_version', 128)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['app_id', 'installation_id'], 'installations_unique_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_installations');
    }
};
