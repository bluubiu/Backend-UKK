<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Change default value for future records
        Schema::table('users', function (Blueprint $table) {
            $table->integer('score')->default(100)->change();
        });

        // 2. Fix existing users who have 0 score (likely created after schema reset but before fix)
        // Only update 'peminjam' (role_id 3) or users who shouldn't have 0.
        // Assuming anyone with 0 score is an error state for a new user.
        DB::table('users')
            ->where('score', 0)
            ->update(['score' => 100]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('score')->default(0)->change();
        });
    }
};
