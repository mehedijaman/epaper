<?php

namespace Database\Seeders;

use App\Models\AdSlot;
use Illuminate\Database\Seeder;

class AdSlotSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (range(1, 8) as $slotNo) {
            AdSlot::query()->updateOrCreate(
                ['slot_no' => $slotNo],
                ['title' => sprintf('Slot %d', $slotNo)],
            );
        }
    }
}
