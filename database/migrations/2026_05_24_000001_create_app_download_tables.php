<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_distribution_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('app_key', 64)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('v2_app_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('v2_app_versions', 'app_id')) {
                $table->unsignedBigInteger('app_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            }
        });

        try {
            Schema::table('v2_app_versions', function (Blueprint $table) {
                $table->dropUnique('app_versions_unique_build');
            });
        } catch (Throwable $e) {
            // Some installs may not have run the earlier app-version migration.
        }

        Schema::table('v2_app_versions', function (Blueprint $table) {
            $table->unique(
                ['app_id', 'platform', 'channel', 'arch', 'build_number'],
                'app_versions_unique_app_build'
            );
        });

        Schema::create('v2_app_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_version_id')
                ->constrained('v2_app_versions')
                ->cascadeOnDelete();
            $table->string('disk', 64)->default('app_downloads');
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('extension', 16);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('v2_user')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('v2_app_download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')
                ->constrained('v2_distribution_apps')
                ->cascadeOnDelete();
            $table->foreignId('app_version_id')
                ->constrained('v2_app_versions')
                ->cascadeOnDelete();
            $table->foreignId('app_artifact_id')
                ->constrained('v2_app_artifacts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('v2_user')
                ->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_app_download_logs');
        Schema::dropIfExists('v2_app_artifacts');

        try {
            Schema::table('v2_app_versions', function (Blueprint $table) {
                $table->dropUnique('app_versions_unique_app_build');
            });
        } catch (Throwable $e) {
            //
        }

        Schema::table('v2_app_versions', function (Blueprint $table) {
            if (Schema::hasColumn('v2_app_versions', 'app_id')) {
                $table->dropIndex(['app_id']);
                $table->dropColumn('app_id');
            }
        });

        Schema::dropIfExists('v2_distribution_apps');
    }
};
