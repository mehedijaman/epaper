<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class SeedEpaperCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'epaper:seed-categories {count=16 : Number of category slots to seed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed ePaper page-slot categories using deterministic positions (idempotent).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count < 1) {
            $this->error('The count argument must be a positive integer.');

            return self::INVALID;
        }

        $created = 0;
        $updated = 0;

        foreach (range(1, $count) as $position) {
            $category = Category::query()->updateOrCreate(
                ['position' => $position],
                [
                    'name' => sprintf('Page %d', $position),
                    'is_active' => true,
                ],
            );

            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->components->info(sprintf(
            'ePaper categories seeded successfully. Total: %d, Created: %d, Updated: %d',
            $count,
            $created,
            $updated,
        ));

        return self::SUCCESS;
    }
}
