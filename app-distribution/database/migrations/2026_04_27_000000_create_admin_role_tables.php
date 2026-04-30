<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->unique(['admin_role_id', 'admin_permission_id'], 'role_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
    }
};
