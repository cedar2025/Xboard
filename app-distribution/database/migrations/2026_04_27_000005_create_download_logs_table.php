<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('app_version_id')->constrained('app_versions')->cascadeOnDelete();
            $table->foreignId('app_artifact_id')->constrained('app_artifacts')->cascadeOnDelete();
            $table->string('installation_id', 128)->nullable()->index();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
    }
};
