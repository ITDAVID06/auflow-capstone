<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only core seeders run here — safe for production.
     *
     * To seed mock / test data explicitly run:
     *   php artisan db:seed --class=MockTestDataSeeder
     *   php artisan db:seed --class=AccountSeeder
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            AdminAccountSeeder::class,
        ]);
    }
}
