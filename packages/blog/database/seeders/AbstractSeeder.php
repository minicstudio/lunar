<?php

namespace Lunar\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

abstract class AbstractSeeder extends Seeder
{
    /**
     * Get the seed data from the JSON file
     *
     * @param  mixed  $file
     */
    protected function getSeedData($file): Collection
    {
        $path = __DIR__ . "/data/{$file}.json";

        return collect(json_decode(File::get($path)));
    }
}
