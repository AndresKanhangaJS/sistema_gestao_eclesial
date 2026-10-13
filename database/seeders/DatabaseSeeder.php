<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Dados de referencia: necessarios em qualquer ambiente.
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            CatequeseSeeder::class,
        ]);

        // Dados de teste/demonstracao (utilizadores com senha "password",
        // faker): NUNCA em producao.
        if (! app()->environment('production')) {
            $this->call([
                TestDataSeeder::class,
                DemoDataSeeder::class,
                CatequeseDemoDataSeeder::class,
            ]);
        }
    }
}
