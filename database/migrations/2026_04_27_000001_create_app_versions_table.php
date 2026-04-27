<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32)->index();
            $table->string('channel', 32)->default('stable')->index();
            $table->string('arch', 32)->nullable()->index();
            $table->string('version', 32);
            $table->unsignedInteger('build_number');
            $table->unsignedInteger('min_supported_build')->default(0);
            $table->string('download_url', 2048);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->text('release_notes')->nullable();
            $table->boolean('is_force')->default(false);
            $table->boolean('is_enabled')->default(false)->index();
            $table->unsignedInteger('published_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['platform', 'channel', 'arch', 'build_number'], 'app_versions_unique_build');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_app_versions');
    }
};
