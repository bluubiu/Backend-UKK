<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('return_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns');
            $table->integer('completeness'); // 1-5
            $table->integer('functionality'); // 1-5
            $table->integer('cleanliness'); // 1-5
            $table->integer('physical_damage'); // 1-5, lower is better
            $table->boolean('on_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_checklists');
    }
};
