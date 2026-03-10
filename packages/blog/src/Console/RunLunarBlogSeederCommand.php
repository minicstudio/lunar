<?php

namespace Lunar\Blog\Console;

use Illuminate\Console\Command;
use Lunar\Blog\Database\Seeders\BlogSeeder;

class RunLunarBlogSeederCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'lunar:seed-blog';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Seed the database with the Lunar package blog data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Run the seeder to set up the basics
        $this->info('Seeding the database');
        $this->call('db:seed', ['--class' => BlogSeeder::class]);

        return self::SUCCESS;
    }
}
