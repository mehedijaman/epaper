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
        Schema::table('editions', function (Blueprint $table): void {
            $table->dropUnique('editions_edition_date_unique');
            $table->index('edition_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table): void {
            $table->dropIndex('editions_edition_date_index');
            $table->unique('edition_date');
        });
    }
};
