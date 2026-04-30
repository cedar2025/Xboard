<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_installation_id')->constrained('device_installations')->cascadeOnDelete();
            $table->string('version', 32)->nullable();
            $table->unsignedInteger('build_number')->default(0);
            $table->string('os_version', 128)->nullable();
            $table->timestamp('reported_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
