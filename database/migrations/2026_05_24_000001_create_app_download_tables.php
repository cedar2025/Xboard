<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_distribution_apps')) {
            Schema::create('v2_distribution_apps', function (Blueprint $table) {
                $table->id();
                $table->string('name', 128);
                $table->string('app_key', 64)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('v2_app_versions', 'app_id')) {
            Schema::table('v2_app_versions', function (Blueprint $table) {
                $table->unsignedBigInteger('app_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            });
        }

        try {
            Schema::table('v2_app_versions', function (Blueprint $table) {
                $table->dropUnique('app_versions_unique_build');
            });
        } catch (Throwable $e) {
            // Some installs may not have run the earlier app-version migration.
        }

        try {
            Schema::table('v2_app_versions', function (Blueprint $table) {
                $table->unique(
                    ['app_id', 'platform', 'channel', 'arch', 'build_number'],
                    'app_versions_unique_app_build'
                );
            });
        } catch (Throwable $e) {
            // The migration may be resumed after a partial production run.
        }

        if (!Schema::hasTable('v2_app_artifacts')) {
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
                $table->integer('uploaded_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('v2_app_download_logs')) {
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
                $table->integer('user_id')->nullable()->index();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('downloaded_at')->index();
                $table->timestamps();
            });
        }
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
