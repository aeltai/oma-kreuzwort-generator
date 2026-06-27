<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('puzzles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('language')->default('de');
            $table->longText('data_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puzzles');
    }
};
