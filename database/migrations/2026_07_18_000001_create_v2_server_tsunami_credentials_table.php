<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_server_tsunami_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id')->unique();
            $table->char('credential_hash', 64)->nullable()->unique();
            $table->char('enrollment_hash', 64)->nullable()->unique();
            $table->timestamp('enrollment_expires_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')
                ->references('id')
                ->on('v2_server')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_server_tsunami_credentials');
    }
};
