<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('update_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('app_version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->string('installation_id', 128)->nullable()->index();
            $table->string('event', 64)->index();
            $table->string('platform', 32)->nullable();
            $table->string('channel', 32)->nullable();
            $table->string('from_version', 32)->nullable();
            $table->unsignedInteger('from_build')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_events');
    }
};
