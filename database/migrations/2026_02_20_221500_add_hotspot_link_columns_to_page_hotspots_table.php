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
        $hasTargetHotspotColumn = Schema::hasColumn('page_hotspots', 'target_hotspot_id');
        $hasLinkedHotspotColumn = Schema::hasColumn('page_hotspots', 'linked_hotspot_id');

        if ($hasTargetHotspotColumn && $hasLinkedHotspotColumn) {
            return;
        }

        Schema::table('page_hotspots', function (Blueprint $table) use ($hasTargetHotspotColumn, $hasLinkedHotspotColumn): void {
            if (! $hasTargetHotspotColumn) {
                $table->unsignedBigInteger('target_hotspot_id')
                    ->nullable()
                    ->after('target_page_no');
                $table->index('target_hotspot_id');
            }

            if (! $hasLinkedHotspotColumn) {
                $table->unsignedBigInteger('linked_hotspot_id')
                    ->nullable()
                    ->after('target_hotspot_id');
                $table->index('linked_hotspot_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasTargetHotspotColumn = Schema::hasColumn('page_hotspots', 'target_hotspot_id');
        $hasLinkedHotspotColumn = Schema::hasColumn('page_hotspots', 'linked_hotspot_id');

        if (! $hasTargetHotspotColumn && ! $hasLinkedHotspotColumn) {
            return;
        }

        Schema::table('page_hotspots', function (Blueprint $table) use ($hasTargetHotspotColumn, $hasLinkedHotspotColumn): void {
            if ($hasLinkedHotspotColumn) {
                $table->dropIndex(['linked_hotspot_id']);
                $table->dropColumn('linked_hotspot_id');
            }

            if ($hasTargetHotspotColumn) {
                $table->dropIndex(['target_hotspot_id']);
                $table->dropColumn('target_hotspot_id');
            }
        });
    }
};
