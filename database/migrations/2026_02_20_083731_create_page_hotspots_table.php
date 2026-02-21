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
        Schema::create('page_hotspots', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['relation'])->default('relation');
            $table->enum('relation_kind', ['next', 'previous'])->nullable();
            $table->unsignedSmallInteger('target_page_no')->nullable();
            $table->decimal('x', 8, 6);
            $table->decimal('y', 8, 6);
            $table->decimal('w', 8, 6);
            $table->decimal('h', 8, 6);
            $table->string('label', 150)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['page_id', 'relation_kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_hotspots');
    }
};
