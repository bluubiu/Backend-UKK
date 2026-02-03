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
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->timestamp('estimated_available_at')->nullable()->after('requested_at');
            $table->foreignId('loan_id')->nullable()->after('status')->constrained('loans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_lists', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropColumn(['estimated_available_at', 'loan_id']);
        });
    }
};
