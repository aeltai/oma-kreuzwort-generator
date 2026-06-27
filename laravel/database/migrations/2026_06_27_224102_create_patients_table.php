<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('language')->default('de');
            $table->string('difficulty')->default('leicht');
            $table->text('family_story')->default('');
            $table->text('custom_context')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
