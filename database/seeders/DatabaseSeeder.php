<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with roles, granular permissions, and foundation accounts.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            PropertySeeder::class,
            DealSeeder::class,
            WhatsAppSeeder::class,
            KnowledgeRecordSeeder::class,
        ]);
    }
}
