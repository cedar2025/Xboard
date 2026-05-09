<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('subject', 255);
            $table->longText('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_mail_templates');
    }
};
