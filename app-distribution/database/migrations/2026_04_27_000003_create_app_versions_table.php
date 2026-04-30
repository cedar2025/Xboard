<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->string('channel', 32)->default('stable')->index();
            $table->string('arch', 32)->nullable()->index();
            $table->string('version', 32);
            $table->unsignedInteger('build_number');
            $table->unsignedInteger('min_supported_build')->default(0);
            $table->text('release_notes')->nullable();
            $table->boolean('is_force')->default(false);
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['app_id', 'platform', 'channel', 'arch', 'build_number'], 'versions_unique_build');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
