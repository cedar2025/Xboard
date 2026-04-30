<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_version_id')->constrained('app_versions')->cascadeOnDelete();
            $table->string('disk', 64)->default('artifacts');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_artifacts');
    }
};
