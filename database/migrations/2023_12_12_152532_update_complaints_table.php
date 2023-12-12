<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // Change the default value of 'status' to 'open'
            $table->string('status')->default('open')->change();

            // Drop 'solved_date' column if it exists
            if (Schema::hasColumn('complaints', 'solved_date')) {
                $table->dropColumn('solved_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();

            // Add 'solved_date' column back
            $table->date('solved_date')->nullable();
        });
    }
};