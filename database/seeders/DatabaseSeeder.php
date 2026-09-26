<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BonbonBaseSeeder::class);

        // Les modules qui ont des données de référence fournissent leur propre seeder (ex. allergènes UE).
        foreach (glob(base_path('modules/*/Database/Seeders/*Seeder.php')) as $file) {
            $this->call('Modules\\'.basename(dirname($file, 3)).'\\Database\\Seeders\\'.basename($file, '.php'));
        }
    }
}
