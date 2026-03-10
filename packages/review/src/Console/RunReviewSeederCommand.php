<?php

namespace Lunar\Review\Console;

use Illuminate\Console\Command;
use Lunar\Review\Database\Seeders\ReviewAttributeSeeder;

class RunReviewSeederCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'lunar:seed-review';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Seed the database with the Lunar package review attributes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Run the seeder to set up the basics
        $this->info('Seeding review attributes for the Lunar package');
        $this->call('db:seed', ['--class' => ReviewAttributeSeeder::class]);

        return self::SUCCESS;
    }
}
